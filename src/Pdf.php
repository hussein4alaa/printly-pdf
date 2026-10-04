<?php

namespace g4t\Printly;

use g4t\Printly\Exceptions\InvalidFont;
use g4t\Printly\Fonts\FontRegistry;
use g4t\Printly\Support\Color;
use g4t\Printly\Support\HtmlPage;
use g4t\Printly\Support\Length;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use RuntimeException;

class Pdf implements Responsable
{
    use Conditionable, Macroable;

    /** Paper sizes in millimetres (portrait). */
    public const FORMATS = [
        'a3' => [297, 420],
        'a4' => [210, 297],
        'a5' => [148, 210],
        'a6' => [105, 148],
        'letter' => [215.9, 279.4],
        'legal' => [215.9, 355.6],
        'tabloid' => [279.4, 431.8],
    ];

    private const GENERIC_FONTS = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui'];

    protected ?string $html = null;

    protected ?string $view = null;

    /** @var array<string, mixed> */
    protected array $viewData = [];

    /** @var array{0: float, 1: float} Paper width and height in inches (portrait). */
    protected array $paper;

    protected bool $landscape = false;

    /** @var array{0: float, 1: float, 2: float, 3: float} Top, right, bottom, left in inches. */
    protected array $margins = [0, 0, 0, 0];

    protected ?string $direction = null;

    protected ?string $lang = null;

    protected ?string $font = null;

    protected ?string $fontSize = null;

    protected ?string $color = null;

    protected ?string $background = null;

    protected ?string $title = null;

    protected ?string $header = null;

    protected ?string $footer = null;

    protected ?string $watermark = null;

    /** @var list<string> */
    protected array $css = [];

    protected float $scale = 1.0;

    protected ?string $pageRanges = null;

    protected FontRegistry $fonts;

    public function __construct(protected PrintlyManager $manager)
    {
        $this->fonts = clone $manager->fonts();

        $defaults = $manager->config('defaults', []);

        $this->format($defaults['format'] ?? 'A4');
        $this->orientation($defaults['orientation'] ?? 'portrait');
        $this->margins(...(array) ($defaults['margins'] ?? 15));

        if (in_array($defaults['direction'] ?? 'auto', ['rtl', 'ltr'], true)) {
            $this->direction($defaults['direction']);
        }

        foreach (['lang', 'font', 'color', 'background'] as $option) {
            if (! empty($defaults[$option])) {
                $this->{$option}($defaults[$option]);
            }
        }

        if (! empty($defaults['font_size'])) {
            $this->fontSize($defaults['font_size']);
        }
    }

    // ------------------------------------------------------------------
    // Content
    // ------------------------------------------------------------------

    public function usingHtml(string $html): static
    {
        $this->html = $html;
        $this->view = null;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function usingView(string $view, array $data = []): static
    {
        $this->view = $view;
        $this->viewData = $data;
        $this->html = null;

        return $this;
    }

    /**
     * Extra CSS appended after the package's base styles.
     */
    public function css(string $css): static
    {
        $this->css[] = $css;

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    // ------------------------------------------------------------------
    // Language & direction
    // ------------------------------------------------------------------

    public function rtl(): static
    {
        return $this->direction('rtl');
    }

    public function ltr(): static
    {
        return $this->direction('ltr');
    }

    public function direction(string $direction): static
    {
        if (! in_array($direction, ['rtl', 'ltr'], true)) {
            throw new InvalidArgumentException("Direction must be rtl or ltr, [{$direction}] given.");
        }

        $this->direction = $direction;

        return $this;
    }

    /**
     * Document language (e.g. "ar"). Unless a direction is set explicitly,
     * RTL languages switch the document to right-to-left automatically.
     */
    public function lang(string $lang): static
    {
        if (! preg_match('/^[A-Za-z0-9-]+$/', $lang)) {
            throw new InvalidArgumentException("Invalid language code [{$lang}].");
        }

        $this->lang = $lang;

        return $this;
    }

    // ------------------------------------------------------------------
    // Fonts
    // ------------------------------------------------------------------

    /**
     * Default font family for the document: a registered font or any font
     * installed on the machine that runs Chrome.
     */
    public function font(string $family, int|float|string|null $size = null): static
    {
        if (! preg_match('/^[\p{L}\p{N} _-]+$/u', $family)) {
            throw InvalidFont::invalidFamily($family);
        }

        $this->font = trim($family);

        return $size === null ? $this : $this->fontSize($size);
    }

    /**
     * Base font size. Bare numbers are points.
     */
    public function fontSize(int|float|string $size): static
    {
        $this->fontSize = Length::css($size, 'pt');

        return $this;
    }

    /**
     * Register a font from files or font-file URLs for this PDF only.
     *
     * @param  string|array<string|int, string>  $sources
     *
     * @see FontRegistry::register()
     */
    public function registerFont(string $family, string|array $sources): static
    {
        $this->fonts->register($family, $sources);

        return $this;
    }

    /**
     * Load a Google Fonts family for this PDF only (needs network access at render time).
     *
     * @param  list<int>  $weights
     */
    public function googleFont(string $family, array $weights = [400, 700]): static
    {
        $this->fonts->google($family, $weights);

        return $this;
    }

    // ------------------------------------------------------------------
    // Colors
    // ------------------------------------------------------------------

    /**
     * Default text color.
     *
     * @param  string|array<int, int|float>  $color
     */
    public function color(string|array $color): static
    {
        $this->color = Color::parse($color);

        return $this;
    }

    /**
     * Page background color, painted edge to edge including the margins.
     *
     * @param  string|array<int, int|float>  $color
     */
    public function background(string|array $color): static
    {
        $this->background = Color::parse($color);

        return $this;
    }

    // ------------------------------------------------------------------
    // Page setup
    // ------------------------------------------------------------------

    public function format(string $format): static
    {
        $size = self::FORMATS[strtolower($format)] ?? null;

        if ($size === null) {
            throw new InvalidArgumentException("Unknown paper format [{$format}]. Supported: ".implode(', ', array_keys(self::FORMATS)).'.');
        }

        return $this->paperSize($size[0], $size[1]);
    }

    /**
     * Custom paper size. Bare numbers are millimetres.
     */
    public function paperSize(int|float|string $width, int|float|string $height): static
    {
        $this->paper = [Length::inches($width), Length::inches($height)];

        return $this;
    }

    public function landscape(): static
    {
        $this->landscape = true;

        return $this;
    }

    public function portrait(): static
    {
        $this->landscape = false;

        return $this;
    }

    public function orientation(string $orientation): static
    {
        if (! in_array($orientation, ['portrait', 'landscape'], true)) {
            throw new InvalidArgumentException("Orientation must be portrait or landscape, [{$orientation}] given.");
        }

        $this->landscape = $orientation === 'landscape';

        return $this;
    }

    /**
     * Page margins, CSS shorthand style: (all), (vertical, horizontal),
     * (top, horizontal, bottom) or (top, right, bottom, left). Bare numbers are millimetres.
     */
    public function margins(
        int|float|string $top,
        int|float|string|null $right = null,
        int|float|string|null $bottom = null,
        int|float|string|null $left = null,
    ): static {
        $right ??= $top;
        $bottom ??= $top;
        $left ??= $right;

        $this->margins = [Length::inches($top), Length::inches($right), Length::inches($bottom), Length::inches($left)];

        return $this;
    }

    /**
     * HTML repeated at the top of every page, drawn inside the top margin.
     * Placeholders: {page}, {pages}, {date}, {title}.
     */
    public function header(string|Htmlable $html): static
    {
        $this->header = $html instanceof Htmlable ? $html->toHtml() : $html;

        return $this;
    }

    /**
     * HTML repeated at the bottom of every page, drawn inside the bottom margin.
     * Placeholders: {page}, {pages}, {date}, {title}.
     */
    public function footer(string|Htmlable $html): static
    {
        $this->footer = $html instanceof Htmlable ? $html->toHtml() : $html;

        return $this;
    }

    /**
     * Shortcut for a page-number footer, e.g. pageNumbers('صفحة {page} من {pages}').
     */
    public function pageNumbers(string $format = '{page} / {pages}'): static
    {
        return $this->footer(e($format));
    }

    /**
     * Large translucent text behind the content of every page.
     *
     * @param  string|array<int, int|float>  $color
     */
    public function watermark(string $text, string|array $color = '#000000', float $opacity = 0.08, int $angle = -35, int|float|string $size = 90): static
    {
        $this->watermark = sprintf(
            '<div style="position:fixed;top:0;right:0;bottom:0;left:0;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:2147483647;">'
            .'<span style="transform:rotate(%ddeg);font-size:%s;font-weight:700;white-space:nowrap;color:%s;opacity:%s;">%s</span></div>',
            $angle,
            Length::css($size, 'pt'),
            Color::parse($color),
            max(0, min(1, $opacity)),
            e($text),
        );

        return $this;
    }

    public function scale(float $scale): static
    {
        if ($scale < 0.1 || $scale > 2) {
            throw new InvalidArgumentException('Scale must be between 0.1 and 2.');
        }

        $this->scale = $scale;

        return $this;
    }

    /**
     * Limit the output to some pages, e.g. "1-3, 5".
     */
    public function pages(string $ranges): static
    {
        $this->pageRanges = $ranges;

        return $this;
    }

    // ------------------------------------------------------------------
    // Output
    // ------------------------------------------------------------------

    /**
     * The complete HTML document that is sent to the driver.
     */
    public function toHtml(): string
    {
        $body = $this->body();
        $css = $this->styles();
        $families = $this->usedFonts($body.$css);

        $head = '<meta charset="utf-8">'
            .($this->title !== null ? '<title>'.e($this->title).'</title>' : '')
            .$this->fonts->links($families)
            .'<style>'.$this->fonts->css($families).$this->baseCss().'</style>'
            .($css !== '' ? '<style>'.$css.'</style>' : '');

        return HtmlPage::compose($body, $head, $this->resolveLang(), $this->resolveDirection(), $this->marginFontPreload().$this->watermark);
    }

    public function options(): RenderOptions
    {
        $hasMarginContent = $this->header !== null || $this->footer !== null;

        return new RenderOptions(
            paperWidth: $this->paper[0],
            paperHeight: $this->paper[1],
            landscape: $this->landscape,
            marginTop: $this->margins[0],
            marginRight: $this->margins[1],
            marginBottom: $this->margins[2],
            marginLeft: $this->margins[3],
            headerHtml: $hasMarginContent ? $this->marginTemplate($this->header) : null,
            footerHtml: $hasMarginContent ? $this->marginTemplate($this->footer) : null,
            scale: $this->scale,
            pageRanges: $this->pageRanges,
        );
    }

    /**
     * Render and return the raw PDF bytes.
     */
    public function content(): string
    {
        return $this->manager->driver()->render($this->toHtml(), $this->options());
    }

    public function base64(): string
    {
        return base64_encode($this->content());
    }

    /**
     * Write the PDF to a local path, or to a filesystem disk when one is given.
     */
    public function save(string $path, ?string $disk = null): static
    {
        $content = $this->content();

        if ($disk !== null) {
            $this->manager->disk($disk)->put($path, $content);

            return $this;
        }

        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory [{$directory}].");
        }

        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException("Unable to write PDF to [{$path}].");
        }

        return $this;
    }

    public function download(string $name = 'document.pdf'): Response
    {
        return $this->response('attachment', $name);
    }

    /**
     * Show the PDF in the browser instead of downloading it.
     */
    public function inline(string $name = 'document.pdf'): Response
    {
        return $this->response('inline', $name);
    }

    /**
     * Returning the PDF from a route or controller displays it inline.
     */
    public function toResponse($request): Response
    {
        return $this->inline();
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    protected function body(): string
    {
        if ($this->view !== null) {
            return $this->manager->renderView($this->view, $this->viewData);
        }

        return $this->html ?? '';
    }

    protected function styles(): string
    {
        return implode("\n", $this->css);
    }

    protected function baseCss(): string
    {
        $css = 'html{-webkit-print-color-adjust:exact;print-color-adjust:exact;}';

        $body = ['margin:0', 'font-family:'.$this->fontStack()];

        if ($this->fontSize !== null) {
            $body[] = 'font-size:'.$this->fontSize;
        }

        if ($this->color !== null) {
            $body[] = 'color:'.$this->color;
        }

        $css .= 'body{'.implode(';', $body).';}';

        if ($this->background !== null) {
            $css .= '@page{background-color:'.$this->background.';}html{background-color:'.$this->background.';}';
        }

        return $css;
    }

    protected function fontStack(): string
    {
        $families = array_filter([$this->font, ...(array) $this->manager->config('fallback_fonts', ['sans-serif'])]);

        return implode(',', array_map(
            fn (string $family) => in_array(strtolower($family), self::GENERIC_FONTS, true) ? $family : "'".str_replace(["'", '\\'], '', $family)."'",
            array_unique($families),
        ));
    }

    /**
     * Registered fonts that the document actually refers to.
     *
     * @return list<string>
     */
    protected function usedFonts(string $html): array
    {
        $families = $this->fonts->usedIn($html.$this->header.$this->footer);

        if ($this->font !== null && $this->fonts->has($this->font) && ! in_array($this->font, $families, true)) {
            $families[] = $this->font;
        }

        return $families;
    }

    protected function resolveLang(): string
    {
        return $this->lang ?? str_replace('_', '-', $this->manager->locale());
    }

    protected function resolveDirection(): string
    {
        if ($this->direction !== null) {
            return $this->direction;
        }

        $language = strtolower(Str::before($this->resolveLang(), '-'));

        return in_array($language, (array) $this->manager->config('rtl_locales', []), true) ? 'rtl' : 'ltr';
    }

    /**
     * Chrome draws header/footer templates in an isolated context that sees none
     * of the page's styles, so the template carries its own fonts and colors.
     */
    protected function marginTemplate(?string $content): string
    {
        if ($content === null || $content === '') {
            return '<span></span>';
        }

        $content = strtr($content, [
            '{page}' => '<span class="pageNumber"></span>',
            '{pages}' => '<span class="totalPages"></span>',
            '{date}' => '<span class="date"></span>',
            '{title}' => '<span class="title"></span>',
        ]);

        $style = sprintf(
            'width:100%%;box-sizing:border-box;margin:0;padding:0 %sin 0 %sin;font-family:%s;font-size:9pt;color:%s;direction:%s;text-align:center;-webkit-print-color-adjust:exact;',
            round($this->margins[1], 4),
            round($this->margins[3], 4),
            $this->fontStack(),
            $this->color ?? '#6b7280',
            $this->resolveDirection(),
        );

        return '<style>'.$this->fonts->css($this->usedFonts($content)).'#header,#footer{padding-left:0 !important;padding-right:0 !important;}</style>'
            .'<div style="'.$style.'">'.$content.'</div>';
    }

    /**
     * Chrome only lets header/footer templates use a web font the page itself
     * has loaded, so every face they may need is touched by an invisible element.
     */
    protected function marginFontPreload(): string
    {
        if ($this->header === null && $this->footer === null) {
            return '';
        }

        $spans = '';

        foreach ($this->fonts->faces($this->usedFonts((string) $this->header.$this->footer)) as $face) {
            $weight = Str::before($face->weight, ' ');
            $spans .= "<span style=\"font-family:'{$face->family}';font-weight:{$weight};font-style:{$face->style};\">.</span>";
        }

        return $spans === ''
            ? ''
            : '<div aria-hidden="true" style="position:absolute;top:0;left:0;width:0;height:0;overflow:hidden;visibility:hidden;">'.$spans.'</div>';
    }

    protected function response(string $disposition, string $name): Response
    {
        $name = Str::finish($name, '.pdf');
        $fallback = str_replace(['%', '/', '\\'], '', Str::ascii($name));

        if (trim($fallback, '. ') === 'pdf' || $fallback === '') {
            $fallback = 'document.pdf';
        }

        $response = new Response($this->content(), 200, ['Content-Type' => 'application/pdf']);

        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition($disposition, str_replace(['/', '\\'], '-', $name), $fallback),
        );

        return $response;
    }
}

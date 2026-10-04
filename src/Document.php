<?php

namespace g4t\Printly;

use g4t\Printly\Support\Color;
use g4t\Printly\Support\Length;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

/**
 * Builds a PDF element by element, without writing HTML. All text is escaped;
 * pass an Htmlable (or use raw()) to opt out. Layout uses logical CSS
 * properties, so the same document flips correctly between RTL and LTR.
 */
class Document extends Pdf
{
    private const ALIGNMENTS = ['start', 'end', 'left', 'right', 'center', 'justify'];

    private const IMAGE_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
    ];

    private const STYLES = <<<'CSS'
        .pdf-doc{--pdf-accent:#2563eb;--pdf-border:#e5e7eb;--pdf-stripe:#f9fafb;line-height:1.6;}
        .pdf-doc *{box-sizing:border-box;}
        .pdf-doc h1,.pdf-doc h2,.pdf-doc h3,.pdf-doc h4,.pdf-doc h5,.pdf-doc h6{margin:0 0 .5em;line-height:1.3;color:var(--pdf-accent);break-after:avoid;}
        .pdf-doc h1{font-size:2em;}.pdf-doc h2{font-size:1.5em;}.pdf-doc h3{font-size:1.25em;}
        .pdf-doc h4{font-size:1.1em;}.pdf-doc h5,.pdf-doc h6{font-size:1em;}
        .pdf-doc p{margin:0 0 .8em;}
        .pdf-doc ul,.pdf-doc ol{margin:0 0 .8em;padding-inline-start:1.5em;}
        .pdf-doc table{width:100%;border-collapse:collapse;margin:0 0 1em;}
        .pdf-doc th,.pdf-doc td{padding:.5em .75em;border:1px solid var(--pdf-border);text-align:start;vertical-align:top;}
        .pdf-doc th{background:var(--pdf-accent);color:#fff;font-weight:700;}
        .pdf-doc tr{break-inside:avoid;}
        .pdf-doc .pdf-striped tbody tr:nth-child(even){background:var(--pdf-stripe);}
        .pdf-doc .pdf-details th{width:30%;background:var(--pdf-stripe);color:inherit;}
        .pdf-doc hr{border:0;border-top:1px solid var(--pdf-border);margin:1em 0;}
        .pdf-doc img{max-width:100%;}
        .pdf-doc .pdf-image{margin:0 0 1em;}
        .pdf-doc .pdf-callout{margin:0 0 1em;padding:.75em 1em;border-inline-start:4px solid var(--pdf-accent);background:var(--pdf-stripe);border-radius:4px;break-inside:avoid;}
        .pdf-doc .pdf-break{break-after:page;}
        CSS;

    /** @var list<string> */
    protected array $elements = [];

    protected ?string $accent = null;

    /**
     * Color used for headings, table headers and callout borders.
     *
     * @param  string|array<int, int|float>  $color
     */
    public function accent(string|array $color): static
    {
        $this->accent = Color::parse($color);

        return $this;
    }

    /**
     * @param  string|array<int, int|float>|null  $color
     */
    public function heading(string|Htmlable $text, int $level = 1, string|array|null $color = null, ?string $align = null): static
    {
        $level = max(1, min(6, $level));

        return $this->element("h{$level}", $this->escape($text), [
            'color' => $color === null ? null : Color::parse($color),
            'text-align' => $this->alignment($align),
        ]);
    }

    /**
     * A paragraph. Line breaks in the text are kept. Bare-number sizes are points.
     *
     * @param  string|array<int, int|float>|null  $color
     * @param  string|array<int, int|float>|null  $background
     */
    public function text(
        string|Htmlable $text,
        string|array|null $color = null,
        int|float|string|null $size = null,
        ?string $align = null,
        bool $bold = false,
        string|array|null $background = null,
    ): static {
        return $this->element('p', $this->escape($text), [
            'color' => $color === null ? null : Color::parse($color),
            'font-size' => $size === null ? null : Length::css($size, 'pt'),
            'text-align' => $this->alignment($align),
            'font-weight' => $bold ? '700' : null,
            'background' => $background === null ? null : Color::parse($background),
        ]);
    }

    /**
     * @param  iterable<string|Htmlable>  $items
     * @param  string|array<int, int|float>|null  $color
     */
    public function list(iterable $items, bool $ordered = false, string|array|null $color = null): static
    {
        $html = '';

        foreach ($items as $item) {
            $html .= '<li>'.$this->escape($item).'</li>';
        }

        return $this->element($ordered ? 'ol' : 'ul', $html, [
            'color' => $color === null ? null : Color::parse($color),
        ]);
    }

    /**
     * A data table. The header row repeats on every page the table spans.
     *
     * @param  array<int, string|Htmlable>  $headers
     * @param  iterable<array<array-key, mixed>>  $rows
     * @param  string|array<int, int|float>|null  $headerBackground
     * @param  string|array<int, int|float>|null  $headerColor
     * @param  bool|string|array<int, int|float>  $striped  true, false, or the stripe color
     * @param  string|array<int, int|float>|null  $borderColor
     */
    public function table(
        array $headers,
        iterable $rows,
        string|array|null $headerBackground = null,
        string|array|null $headerColor = null,
        bool|string|array $striped = true,
        string|array|null $borderColor = null,
    ): static {
        $headStyle = $this->style([
            'background' => $headerBackground === null ? null : Color::parse($headerBackground),
            'color' => $headerColor === null ? null : Color::parse($headerColor),
        ]);

        $head = '';

        foreach ($headers as $header) {
            $head .= "<th{$headStyle}>".$this->escape($header).'</th>';
        }

        $body = '';

        foreach ($rows as $row) {
            $body .= '<tr>';

            foreach ($row as $cell) {
                $body .= '<td>'.$this->escape($cell).'</td>';
            }

            $body .= '</tr>';
        }

        $style = $this->style([
            '--pdf-border' => $borderColor === null ? null : Color::parse($borderColor),
            '--pdf-stripe' => is_bool($striped) ? null : Color::parse($striped),
        ]);

        $class = $striped === false ? '' : ' class="pdf-striped"';

        $this->elements[] = "<table{$class}{$style}>"
            .($head === '' ? '' : "<thead><tr>{$head}</tr></thead>")
            ."<tbody>{$body}</tbody></table>";

        return $this;
    }

    /**
     * A two-column label/value table, e.g. ['رقم الفاتورة' => 'INV-001'].
     *
     * @param  array<string, mixed>  $pairs
     */
    public function details(array $pairs): static
    {
        $rows = '';

        foreach ($pairs as $label => $value) {
            $rows .= '<tr><th>'.e($label).'</th><td>'.$this->escape($value).'</td></tr>';
        }

        $this->elements[] = '<table class="pdf-details"><tbody>'.$rows.'</tbody></table>';

        return $this;
    }

    /**
     * An image from a local file (embedded into the PDF), a URL or a data URI.
     * Bare-number dimensions are millimetres.
     */
    public function image(string $source, int|float|string|null $width = null, int|float|string|null $height = null, ?string $align = null): static
    {
        $image = '<img src="'.e($this->imageSource($source)).'" alt=""'.$this->style([
            'width' => $width === null ? null : Length::css($width),
            'height' => $height === null ? null : Length::css($height),
        ]).'>';

        return $this->element('div', $image, ['text-align' => $this->alignment($align)], 'pdf-image');
    }

    /**
     * A highlighted box for notes and totals.
     *
     * @param  string|array<int, int|float>|null  $background
     * @param  string|array<int, int|float>|null  $color
     * @param  string|array<int, int|float>|null  $borderColor
     */
    public function callout(string|Htmlable $text, string|array|null $background = null, string|array|null $color = null, string|array|null $borderColor = null): static
    {
        return $this->element('div', $this->escape($text), [
            'background' => $background === null ? null : Color::parse($background),
            'color' => $color === null ? null : Color::parse($color),
            'border-color' => $borderColor === null ? null : Color::parse($borderColor),
        ], 'pdf-callout');
    }

    /**
     * @param  string|array<int, int|float>|null  $color
     */
    public function divider(string|array|null $color = null): static
    {
        $this->elements[] = '<hr'.$this->style(['border-color' => $color === null ? null : Color::parse($color)]).'>';

        return $this;
    }

    /**
     * Vertical gap. Bare numbers are millimetres.
     */
    public function space(int|float|string $height = 5): static
    {
        $this->elements[] = '<div style="height:'.Length::css($height).';"></div>';

        return $this;
    }

    public function pageBreak(): static
    {
        $this->elements[] = '<div class="pdf-break"></div>';

        return $this;
    }

    /**
     * Unescaped HTML, for anything the builder does not cover.
     */
    public function raw(string|Htmlable $html): static
    {
        $this->elements[] = $html instanceof Htmlable ? $html->toHtml() : $html;

        return $this;
    }

    protected function body(): string
    {
        return '<div class="pdf-doc"'.$this->style(['--pdf-accent' => $this->accent]).'>'.implode('', $this->elements).'</div>';
    }

    protected function styles(): string
    {
        return self::STYLES."\n".parent::styles();
    }

    /**
     * @param  array<string, string|null>  $styles
     */
    protected function element(string $tag, string $innerHtml, array $styles = [], ?string $class = null): static
    {
        $this->elements[] = "<{$tag}".($class ? " class=\"{$class}\"" : '').$this->style($styles).">{$innerHtml}</{$tag}>";

        return $this;
    }

    /**
     * @param  array<string, string|null>  $styles
     */
    protected function style(array $styles): string
    {
        $css = '';

        foreach (array_filter($styles, fn ($value) => $value !== null) as $property => $value) {
            $css .= "{$property}:{$value};";
        }

        return $css === '' ? '' : ' style="'.e($css).'"';
    }

    protected function escape(mixed $value): string
    {
        if ($value instanceof Htmlable) {
            return $value->toHtml();
        }

        return nl2br(e((string) $value), false);
    }

    protected function alignment(?string $align): ?string
    {
        if ($align !== null && ! in_array($align, self::ALIGNMENTS, true)) {
            throw new InvalidArgumentException("Invalid alignment [{$align}]. Use one of: ".implode(', ', self::ALIGNMENTS).'.');
        }

        return $align;
    }

    protected function imageSource(string $source): string
    {
        if (preg_match('#^(https?://|data:)#i', $source)) {
            return $source;
        }

        $type = self::IMAGE_TYPES[strtolower(pathinfo($source, PATHINFO_EXTENSION))] ?? null;

        if ($type === null || ! is_file($source)) {
            throw new InvalidArgumentException("Image [{$source}] must be an existing png, jpg, gif, webp or svg file, a URL, or a data URI.");
        }

        return 'data:'.$type.';base64,'.base64_encode((string) file_get_contents($source));
    }
}

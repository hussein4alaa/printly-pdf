<?php

namespace g4t\Printly\Fonts;

use g4t\Printly\Exceptions\InvalidFont;

final class FontRegistry
{
    /** @var array<string, list<FontFace>> */
    private array $faces = [];

    /** @var array<string, list<int>> */
    private array $google = [];

    private FontDownloader $downloader;

    public function __construct(?FontDownloader $downloader = null)
    {
        $this->downloader = $downloader ?? new FontDownloader(sys_get_temp_dir().DIRECTORY_SEPARATOR.'printly-fonts');
    }

    /**
     * Register a family from font files or http(s) URLs of font files. URLs are
     * downloaded on first use and cached.
     *
     * A string registers the regular weight. An array maps variants to sources:
     * ['regular' => ..., 'bold' => ..., 'italic' => ..., 'bold_italic' => ..., 300 => ..., 'variable' => ...].
     *
     * @param  string|array<string|int, string>  $sources
     */
    public function register(string $family, string|array $sources): static
    {
        $family = self::validateFamily($family);
        $faces = [];

        foreach (is_array($sources) ? $sources : ['regular' => $sources] as $variant => $source) {
            $faces[] = FontFace::fromVariant($family, $variant, $source);
        }

        $this->faces[$family] = $faces;
        unset($this->google[$family]);

        return $this;
    }

    /**
     * Load a family from Google Fonts when the PDF is rendered (needs network access).
     *
     * @param  list<int>  $weights
     */
    public function google(string $family, array $weights = [400, 700]): static
    {
        $family = self::validateFamily($family);
        $weights = array_values(array_unique(array_map('intval', $weights)));
        sort($weights);

        $this->google[$family] = $weights;
        unset($this->faces[$family]);

        return $this;
    }

    public function has(string $family): bool
    {
        return isset($this->faces[$family]) || isset($this->google[$family]);
    }

    /**
     * @return list<string>
     */
    public function families(): array
    {
        return [...array_keys($this->faces), ...array_keys($this->google)];
    }

    /**
     * Registered families that the given HTML refers to by name. Only these are
     * shipped to Chrome, so unused fonts never bloat the document.
     *
     * @return list<string>
     */
    public function usedIn(string $html): array
    {
        return array_values(array_filter($this->families(), fn (string $family) => stripos($html, $family) !== false));
    }

    /**
     * The file/URL-backed faces of the given families.
     *
     * @param  list<string>  $families
     * @return list<FontFace>
     */
    public function faces(array $families): array
    {
        return array_merge([], ...array_map(fn (string $family) => $this->faces[$family] ?? [], array_values($families)));
    }

    /**
     * @font-face rules for the given file/URL-backed families. Downloads any
     * font registered by URL that is not cached yet.
     *
     * @param  list<string>  $families
     */
    public function css(array $families): string
    {
        return implode('', array_map(fn (FontFace $face) => $face->toCss($this->downloader), $this->faces($families)));
    }

    /**
     * <link> tags for the given Google Fonts families.
     *
     * @param  list<string>  $families
     */
    public function links(array $families): string
    {
        $query = [];

        foreach ($families as $family) {
            if (isset($this->google[$family])) {
                $query[] = 'family='.str_replace(' ', '+', $family).':wght@'.implode(';', $this->google[$family]);
            }
        }

        if ($query === []) {
            return '';
        }

        return '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            .'<link rel="stylesheet" href="https://fonts.googleapis.com/css2?'.implode('&amp;', $query).'">';
    }

    private static function validateFamily(string $family): string
    {
        $family = trim($family);

        if (! preg_match('/^[\p{L}\p{N} _-]+$/u', $family)) {
            throw InvalidFont::invalidFamily($family);
        }

        return $family;
    }
}

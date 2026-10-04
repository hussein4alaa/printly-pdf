<?php

namespace g4t\Printly\Fonts;

use g4t\Printly\Exceptions\InvalidFont;

/**
 * One @font-face rule: a single weight/style of a family, backed by a local
 * file or an http(s) URL. Either way the font is embedded as a data URI.
 */
final class FontFace
{
    private const FORMATS = [
        'ttf' => ['font/ttf', 'truetype'],
        'otf' => ['font/otf', 'opentype'],
        'woff' => ['font/woff', 'woff'],
        'woff2' => ['font/woff2', 'woff2'],
    ];

    private const WEIGHTS = [
        '' => '400', 'regular' => '400', 'normal' => '400', 'thin' => '100', 'extralight' => '200',
        'light' => '300', 'medium' => '500', 'semibold' => '600', 'bold' => '700', 'extrabold' => '800',
        'black' => '900', 'variable' => '100 900',
    ];

    /** @var array<string, string> */
    private static array $encoded = [];

    public function __construct(
        public readonly string $family,
        public readonly string $source,
        public readonly string $weight = '400',
        public readonly string $style = 'normal',
    ) {
        if (! $this->isRemote()) {
            if (! is_file($source)) {
                throw InvalidFont::fileNotFound($family, $source);
            }

            if (! isset(self::FORMATS[strtolower(pathinfo($source, PATHINFO_EXTENSION))])) {
                throw InvalidFont::unsupportedFormat($family, $source);
            }
        }
    }

    /**
     * Variant is a name (regular, bold, italic, bold_italic, light, variable, ...)
     * or a numeric weight with an optional "italic" suffix (300, "300italic").
     */
    public static function fromVariant(string $family, string|int $variant, string $source): self
    {
        $key = str_replace([' ', '_', '-'], '', strtolower((string) $variant));
        $style = 'normal';

        if (str_ends_with($key, 'italic')) {
            $style = 'italic';
            $key = substr($key, 0, -6);
        }

        $weight = self::WEIGHTS[$key] ?? (preg_match('/^[1-9]00$/', $key) ? $key : null);

        if ($weight === null) {
            throw InvalidFont::invalidVariant($family, (string) $variant);
        }

        return new self($family, $source, $weight, $style);
    }

    public function isRemote(): bool
    {
        return (bool) preg_match('#^https?://#i', $this->source);
    }

    /**
     * Fonts registered by URL are downloaded (once) and embedded like local files:
     * a page loaded from disk cannot fetch cross-origin fonts reliably, and
     * Chrome's header/footer templates cannot reach the network at all.
     */
    public function toCss(FontDownloader $downloader): string
    {
        $path = $this->isRemote() ? $downloader->path($this->source) : $this->source;
        [$mime, $format] = self::FORMATS[strtolower(pathinfo($path, PATHINFO_EXTENSION))];

        return "@font-face{font-family:'{$this->family}';src:url(data:{$mime};base64,".self::encoded($path).") format('{$format}');font-weight:{$this->weight};font-style:{$this->style};}";
    }

    private static function encoded(string $path): string
    {
        $key = $path.'|'.filemtime($path);

        return self::$encoded[$key] ??= base64_encode((string) file_get_contents($path));
    }
}

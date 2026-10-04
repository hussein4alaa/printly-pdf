<?php

namespace g4t\Printly;

/**
 * Everything a driver needs besides the HTML itself. All lengths are inches.
 */
final class RenderOptions
{
    public function __construct(
        public readonly float $paperWidth,
        public readonly float $paperHeight,
        public readonly bool $landscape = false,
        public readonly float $marginTop = 0,
        public readonly float $marginRight = 0,
        public readonly float $marginBottom = 0,
        public readonly float $marginLeft = 0,
        public readonly ?string $headerHtml = null,
        public readonly ?string $footerHtml = null,
        public readonly float $scale = 1.0,
        public readonly ?string $pageRanges = null,
    ) {}

    public function hasHeaderOrFooter(): bool
    {
        return $this->headerHtml !== null || $this->footerHtml !== null;
    }
}

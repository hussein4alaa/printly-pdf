<?php

namespace g4t\Printly\Exceptions;

use InvalidArgumentException;

class InvalidFont extends InvalidArgumentException
{
    public static function fileNotFound(string $family, string $path): self
    {
        return new self("Font file for [{$family}] not found at [{$path}].");
    }

    public static function unsupportedFormat(string $family, string $source): self
    {
        return new self("Unsupported font file [{$source}] for [{$family}]. Supported formats: ttf, otf, woff, woff2.");
    }

    public static function invalidFamily(string $family): self
    {
        return new self("Invalid font family name [{$family}]. Use letters, numbers, spaces, dashes and underscores only.");
    }

    public static function invalidVariant(string $family, string $variant): self
    {
        return new self("Unknown font variant [{$variant}] for [{$family}]. Use regular, bold, italic, bold_italic, variable, or a weight like 300 / 300italic.");
    }
}

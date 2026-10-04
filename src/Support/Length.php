<?php

namespace g4t\Printly\Support;

use InvalidArgumentException;

/**
 * Parses lengths such as 15, "15mm", "1.5cm", "1in", "12pt" or "96px".
 * Bare numbers use the default unit of the call site.
 */
final class Length
{
    private const UNITS_PER_INCH = ['in' => 1, 'mm' => 25.4, 'cm' => 2.54, 'pt' => 72, 'px' => 96];

    public static function inches(int|float|string $value, string $defaultUnit = 'mm'): float
    {
        [$number, $unit] = self::split($value, $defaultUnit);

        return $number / self::UNITS_PER_INCH[$unit];
    }

    public static function css(int|float|string $value, string $defaultUnit = 'mm'): string
    {
        [$number, $unit] = self::split($value, $defaultUnit);

        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.').$unit;
    }

    /**
     * @return array{0: float, 1: string}
     */
    private static function split(int|float|string $value, string $defaultUnit): array
    {
        if (is_int($value) || is_float($value)) {
            return [(float) $value, $defaultUnit];
        }

        if (! preg_match('/^(\d+(?:\.\d+)?|\.\d+)\s*(in|mm|cm|pt|px)?$/i', trim($value), $matches)) {
            throw new InvalidArgumentException("Invalid length [{$value}]. Use a number or a value like 15mm, 1.5cm, 1in, 12pt, 96px.");
        }

        return [(float) $matches[1], strtolower($matches[2] ?? $defaultUnit)];
    }
}

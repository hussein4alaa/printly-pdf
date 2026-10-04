<?php

namespace g4t\Printly\Exceptions;

use InvalidArgumentException;

class InvalidColor extends InvalidArgumentException
{
    public static function make(string $color): self
    {
        return new self("Invalid color [{$color}]. Use hex (#1a73e8), rgb()/rgba(), hsl()/hsla(), a CSS color name, or an [r, g, b] array.");
    }
}

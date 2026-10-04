<?php

namespace g4t\Printly\Exceptions;

use RuntimeException;
use Throwable;

class FontDownloadFailed extends RuntimeException
{
    public static function because(string $url, string $reason, ?Throwable $previous = null): self
    {
        return new self("Could not download font from [{$url}]: {$reason}", previous: $previous);
    }

    public static function notAFont(string $url): self
    {
        return new self("The URL [{$url}] did not return a ttf, otf, woff or woff2 font file. For a Google Fonts family use googleFont() instead.");
    }
}

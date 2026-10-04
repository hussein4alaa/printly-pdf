<?php

namespace g4t\Printly\Exceptions;

use RuntimeException;
use Throwable;

class RenderingFailed extends RuntimeException
{
    public static function chromeNotFound(?string $binary, Throwable $previous): self
    {
        $tried = $binary ? "at [{$binary}]" : 'automatically';

        return new self(
            "Could not start Chrome ({$tried}). Install Chrome or Chromium and set PRINTLY_CHROME_BINARY to its path. ".$previous->getMessage(),
            previous: $previous,
        );
    }

    public static function because(Throwable $previous): self
    {
        return new self('Chrome failed to render the PDF: '.$previous->getMessage(), previous: $previous);
    }
}

<?php

namespace g4t\Printly\Contracts;

use g4t\Printly\RenderOptions;

interface Driver
{
    /**
     * Render a complete HTML document and return the raw PDF bytes.
     */
    public function render(string $html, RenderOptions $options): string;
}

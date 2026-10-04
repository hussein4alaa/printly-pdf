<?php

namespace g4t\Printly\Support;

/**
 * Turns user content into the full HTML document handed to the driver.
 * Fragments are wrapped; complete documents get the head block, direction
 * and language injected without disturbing what the author already set.
 */
final class HtmlPage
{
    public static function compose(string $content, string $head, string $lang, string $direction, string $append = ''): string
    {
        if (! preg_match('/<html[\s>]/i', $content)) {
            return '<!DOCTYPE html><html lang="'.$lang.'" dir="'.$direction.'"><head>'.$head.'</head><body>'.$content.$append.'</body></html>';
        }

        $content = preg_replace_callback('/<html\b([^>]*)>/i', function (array $matches) use ($lang, $direction) {
            $attributes = $matches[1];

            if (! preg_match('/\bdir\s*=/i', $attributes)) {
                $attributes .= ' dir="'.$direction.'"';
            }

            if (! preg_match('/\blang\s*=/i', $attributes)) {
                $attributes .= ' lang="'.$lang.'"';
            }

            return '<html'.$attributes.'>';
        }, $content, 1);

        // Injected first so the author's own styles win the cascade.
        $content = preg_match('/<head\b[^>]*>/i', $content)
            ? preg_replace_callback('/<head\b[^>]*>/i', fn (array $matches) => $matches[0].$head, $content, 1)
            : preg_replace_callback('/<html\b[^>]*>/i', fn (array $matches) => $matches[0].'<head>'.$head.'</head>', $content, 1);

        if ($append === '') {
            return $content;
        }

        $bodyEnd = strripos($content, '</body>');

        return $bodyEnd === false
            ? $content.$append
            : substr($content, 0, $bodyEnd).$append.substr($content, $bodyEnd);
    }
}

<?php

namespace g4t\Printly\Fonts;

use g4t\Printly\Exceptions\FontDownloadFailed;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches fonts registered by URL and keeps them on disk, so each URL is
 * downloaded once and rendering never depends on the font host afterwards.
 */
class FontDownloader
{
    /** File signature => extension. */
    private const SIGNATURES = [
        "\x00\x01\x00\x00" => 'ttf',
        'true' => 'ttf',
        'OTTO' => 'otf',
        'wOFF' => 'woff',
        'wOF2' => 'woff2',
    ];

    public function __construct(
        protected string $cachePath,
        protected int $timeout = 15,
        protected ?Factory $http = null,
    ) {}

    /**
     * Local path of the font behind the URL, downloading it on first use.
     */
    public function path(string $url): string
    {
        $base = rtrim($this->cachePath, '/\\').DIRECTORY_SEPARATOR.sha1($url);

        foreach (array_unique(self::SIGNATURES) as $extension) {
            if (is_file("{$base}.{$extension}")) {
                return "{$base}.{$extension}";
            }
        }

        $contents = $this->download($url);

        // The type comes from the bytes, not the URL or headers: links often have
        // no extension, and an error page must never be cached as a font.
        $extension = self::SIGNATURES[substr($contents, 0, 4)] ?? throw FontDownloadFailed::notAFont($url);

        return $this->store("{$base}.{$extension}", $contents, $url);
    }

    protected function download(string $url): string
    {
        try {
            $response = $this->http()->timeout($this->timeout)->get($url);
        } catch (Throwable $e) {
            throw FontDownloadFailed::because($url, $e->getMessage(), $e);
        }

        if (! $response->successful()) {
            throw FontDownloadFailed::because($url, 'the server responded with status '.$response->status().'.');
        }

        return $response->body();
    }

    protected function store(string $path, string $contents, string $url): string
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw FontDownloadFailed::because($url, "unable to create the font cache directory [{$directory}].");
        }

        // Written to a temp name first so a concurrent render never reads a partial file.
        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';

        if (file_put_contents($temporary, $contents) === false || ! rename($temporary, $path)) {
            @unlink($temporary);

            throw FontDownloadFailed::because($url, "unable to write to the font cache directory [{$directory}].");
        }

        return $path;
    }

    protected function http(): Factory
    {
        // Going through the facade when there is an application keeps Http::fake() working.
        return $this->http ?? (Facade::getFacadeApplication() ? Http::getFacadeRoot() : new Factory);
    }
}

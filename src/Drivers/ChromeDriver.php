<?php

namespace g4t\Printly\Drivers;

use g4t\Printly\Contracts\Driver;
use g4t\Printly\Exceptions\RenderingFailed;
use g4t\Printly\RenderOptions;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;
use RuntimeException;
use Throwable;

/**
 * Prints HTML to PDF with a locally installed headless Chrome/Chromium,
 * driven over the DevTools protocol (no Node.js required).
 */
class ChromeDriver implements Driver
{
    /**
     * @param  array{binary?: ?string, no_sandbox?: bool, timeout?: int|float, flags?: list<string>, temp_path?: ?string}  $config
     */
    public function __construct(protected array $config = []) {}

    public function render(string $html, RenderOptions $options): string
    {
        $timeout = (int) (((float) ($this->config['timeout'] ?? 30)) * 1000);
        $file = $this->writeTempFile($html);
        $browser = null;

        try {
            $browser = $this->startBrowser($timeout);

            // Loading from a file (rather than injecting the HTML) lets local
            // images and relative paths resolve, and has no size limit.
            $page = $browser->createPage();
            $page->navigate($this->fileUrl($file))->waitForNavigation(Page::NETWORK_IDLE, $timeout);
            $page->evaluate('document.fonts.ready.then(() => true)')->waitForResponse($timeout);

            return base64_decode($page->pdf($this->pdfOptions($options))->getBase64($timeout));
        } catch (RenderingFailed $e) {
            throw $e;
        } catch (Throwable $e) {
            throw RenderingFailed::because($e);
        } finally {
            try {
                $browser?->close();
            } catch (Throwable) {
                // Chrome already exited; nothing left to clean up.
            }

            @unlink($file);
        }
    }

    protected function startBrowser(int $timeout): Browser
    {
        $binary = $this->config['binary'] ?? null ?: null;

        try {
            return (new BrowserFactory($binary))->createBrowser([
                'headless' => true,
                'noSandbox' => (bool) ($this->config['no_sandbox'] ?? false),
                'startupTimeout' => max(1, (int) ($timeout / 1000)),
                'sendSyncDefaultTimeout' => $timeout,
                'customFlags' => ['--allow-file-access-from-files', ...($this->config['flags'] ?? [])],
            ]);
        } catch (Throwable $e) {
            throw RenderingFailed::chromeNotFound($binary, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function pdfOptions(RenderOptions $options): array
    {
        $pdf = [
            'printBackground' => true,
            'preferCSSPageSize' => true,
            'landscape' => $options->landscape,
            'paperWidth' => $options->paperWidth,
            'paperHeight' => $options->paperHeight,
            'marginTop' => $options->marginTop,
            'marginRight' => $options->marginRight,
            'marginBottom' => $options->marginBottom,
            'marginLeft' => $options->marginLeft,
            'scale' => $options->scale,
            'displayHeaderFooter' => $options->hasHeaderOrFooter(),
        ];

        if ($options->hasHeaderOrFooter()) {
            $pdf['headerTemplate'] = $options->headerHtml ?? '<span></span>';
            $pdf['footerTemplate'] = $options->footerHtml ?? '<span></span>';
        }

        if ($options->pageRanges !== null) {
            $pdf['pageRanges'] = $options->pageRanges;
        }

        return $pdf;
    }

    protected function writeTempFile(string $html): string
    {
        $directory = rtrim($this->config['temp_path'] ?? null ?: sys_get_temp_dir(), '/\\');

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create temp directory [{$directory}].");
        }

        $file = $directory.DIRECTORY_SEPARATOR.'printly-'.bin2hex(random_bytes(12)).'.html';

        if (file_put_contents($file, $html) === false) {
            throw new RuntimeException("Unable to write temp file [{$file}].");
        }

        return $file;
    }

    protected function fileUrl(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return 'file://'.(str_starts_with($path, '/') ? '' : '/').implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}

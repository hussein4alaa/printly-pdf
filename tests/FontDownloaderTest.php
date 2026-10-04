<?php

namespace g4t\Printly\Tests;

use g4t\Printly\Exceptions\FontDownloadFailed;
use g4t\Printly\Facades\Printly;
use g4t\Printly\PrintlyManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;

class FontDownloaderTest extends TestCase
{
    private const TTF = "\x00\x01\x00\x00fake-truetype";

    private const WOFF2 = 'wOF2fake-woff2';

    private string $cache;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->cache = sys_get_temp_dir().'/printly-fonts-test-'.uniqid();

        $app['config']->set('printly.font_cache_path', $this->cache);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->cache);

        parent::tearDown();
    }

    public function test_fonts_registered_by_url_are_downloaded_and_embedded(): void
    {
        Http::fake(['fonts.test/*' => Http::response(self::TTF)]);

        $html = Printly::html('<p>نص</p>')
            ->registerFont('Cairo', 'https://fonts.test/Cairo-Regular.ttf')
            ->font('Cairo')
            ->toHtml();

        $this->assertStringContainsString(
            "@font-face{font-family:'Cairo';src:url(data:font/ttf;base64,".base64_encode(self::TTF).") format('truetype')",
            $html,
        );
        $this->assertStringNotContainsString('fonts.test', $html);
    }

    public function test_each_url_is_downloaded_once(): void
    {
        Http::fake(['fonts.test/*' => Http::response(self::TTF)]);

        Printly::registerFont('Cairo', 'https://fonts.test/Cairo-Regular.ttf');

        Printly::html('x')->font('Cairo')->toHtml();
        Printly::html('y')->font('Cairo')->toHtml();

        // A fresh manager (a new request) reuses the file on disk too.
        $this->app->forgetInstance(PrintlyManager::class);
        Printly::clearResolvedInstances();
        Printly::html('z')->registerFont('Cairo', 'https://fonts.test/Cairo-Regular.ttf')->font('Cairo')->toHtml();

        Http::assertSentCount(1);
    }

    public function test_the_format_is_detected_from_the_file_not_the_url(): void
    {
        Http::fake(['fonts.test/*' => Http::response(self::WOFF2)]);

        $html = Printly::html('x')->registerFont('Cairo', ['bold' => 'https://fonts.test/download?id=42'])->font('Cairo')->toHtml();

        $this->assertStringContainsString('src:url(data:font/woff2;base64,'.base64_encode(self::WOFF2).") format('woff2');font-weight:700", $html);
    }

    public function test_urls_work_from_config_and_in_headers(): void
    {
        Http::fake(['fonts.test/*' => Http::response(self::TTF)]);
        config(['printly.fonts' => ['Cairo' => 'https://fonts.test/Cairo.ttf']]);
        $this->app->forgetInstance(PrintlyManager::class);
        Printly::clearResolvedInstances();

        $options = Printly::html('x')->header('<span style="font-family:Cairo">شركة</span>')->options();

        $this->assertStringContainsString("@font-face{font-family:'Cairo';src:url(data:font/ttf;base64,", $options->headerHtml);
    }

    public function test_unused_url_fonts_are_never_downloaded(): void
    {
        Http::fake();

        Printly::html('x')->registerFont('Cairo', 'https://fonts.test/Cairo.ttf')->toHtml();

        Http::assertNothingSent();
    }

    public function test_a_url_that_is_not_a_font_is_rejected_and_not_cached(): void
    {
        Http::fake(['fonts.test/*' => Http::response('<html>Not a font</html>')]);

        try {
            Printly::html('x')->registerFont('Cairo', 'https://fonts.test/css2?family=Cairo')->font('Cairo')->toHtml();
            $this->fail('Expected FontDownloadFailed.');
        } catch (FontDownloadFailed $e) {
            $this->assertStringContainsString('did not return a ttf, otf, woff or woff2 font file', $e->getMessage());
        }

        $this->assertSame([], glob($this->cache.'/*') ?: []);
    }

    public function test_failed_downloads_report_the_url(): void
    {
        Http::fake(['fonts.test/*' => Http::response('', 404)]);

        $this->expectException(FontDownloadFailed::class);
        $this->expectExceptionMessage('[https://fonts.test/missing.ttf]: the server responded with status 404.');

        Printly::html('x')->registerFont('Cairo', 'https://fonts.test/missing.ttf')->font('Cairo')->toHtml();
    }
}

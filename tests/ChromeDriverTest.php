<?php

namespace g4t\Printly\Tests;

use g4t\Printly\Exceptions\RenderingFailed;
use g4t\Printly\Facades\Printly;
use g4t\Printly\PrintlyManager;
use HeadlessChromium\AutoDiscover;

/**
 * Renders through a real Chrome. Skipped on machines without one.
 */
class ChromeDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $binary = env('PRINTLY_CHROME_BINARY') ?: (new AutoDiscover)->guessChromeBinaryPath();

        if (! is_executable($binary) && ! trim((string) @shell_exec('command -v '.escapeshellarg($binary).' 2>/dev/null'))) {
            $this->markTestSkipped('Chrome is not installed.');
        }
    }

    public function test_it_renders_an_arabic_pdf(): void
    {
        $rows = array_map(fn (int $i) => ["منتج {$i}", $i], range(1, 80));

        $content = Printly::make()
            ->lang('ar')
            ->title('فاتورة')
            ->background('#fffbeb')
            ->heading('فاتورة ضريبية')
            ->table(['المنتج', 'الكمية'], $rows)
            ->pageNumbers('صفحة {page} من {pages}')
            ->content();

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(1, preg_match_all('#/Type\s*/Page\b#', $content), 'An 80-row table should span several pages.');
    }

    public function test_paper_size_and_orientation_are_applied(): void
    {
        $content = Printly::html('<p>x</p>')->format('A5')->landscape()->content();

        // A5 landscape is 595 x 420 points.
        $this->assertMatchesRegularExpression('#/MediaBox\s*\[\s*0 0 59[45](\.\d+)? 4(19|20)(\.\d+)?\s*\]#', $content);
    }

    public function test_a_missing_binary_fails_with_a_helpful_message(): void
    {
        config(['printly.chrome.binary' => '/nope/chrome']);
        $this->app->forgetInstance(PrintlyManager::class);
        Printly::clearResolvedInstances();

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessage('PRINTLY_CHROME_BINARY');

        Printly::html('x')->content();
    }
}

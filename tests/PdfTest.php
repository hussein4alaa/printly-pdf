<?php

namespace g4t\Printly\Tests;

use g4t\Printly\Exceptions\InvalidColor;
use g4t\Printly\Facades\Printly;
use g4t\Printly\PrintlyManager;
use g4t\Printly\RenderOptions;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class PdfTest extends TestCase
{
    public function test_fragments_are_wrapped_in_a_complete_document(): void
    {
        $html = Printly::html('<h1>Hello</h1>')->title('My <Title>')->toHtml();

        $this->assertStringStartsWith('<!DOCTYPE html><html lang="en" dir="ltr"><head><meta charset="utf-8">', $html);
        $this->assertStringContainsString('<title>My &lt;Title&gt;</title>', $html);
        $this->assertStringContainsString('<body><h1>Hello</h1></body>', $html);
    }

    public function test_arabic_documents_are_right_to_left(): void
    {
        $this->assertStringContainsString('<html lang="en" dir="rtl">', Printly::html('مرحبا')->rtl()->toHtml());
        $this->assertStringContainsString('<html lang="ar" dir="rtl">', Printly::html('مرحبا')->lang('ar')->toHtml());
        $this->assertStringContainsString('<html lang="ar-IQ" dir="ltr">', Printly::html('مرحبا')->lang('ar-IQ')->ltr()->toHtml());
    }

    public function test_direction_follows_the_application_locale(): void
    {
        config(['app.locale' => 'ar']);

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', Printly::html('مرحبا')->toHtml());
    }

    public function test_complete_documents_keep_their_own_markup(): void
    {
        $html = Printly::html('<html dir="ltr"><head><style>h1{color:red}</style></head><body><header>x</header><h1>Hi</h1></body></html>')
            ->rtl()
            ->lang('ar')
            ->color('#111111')
            ->watermark('DRAFT')
            ->toHtml();

        $this->assertStringContainsString('<html dir="ltr" lang="ar">', $html);
        $this->assertSame(1, substr_count($html, '<head>'));
        $this->assertLessThan(strpos($html, 'h1{color:red}'), strpos($html, 'color:#111111'), 'Base styles must come before the author styles.');
        $this->assertMatchesRegularExpression('#DRAFT</span></div></body></html>$#', $html);
    }

    public function test_documents_without_a_head_get_one(): void
    {
        $html = Printly::html('<html><body>Hi</body></html>')->toHtml();

        $this->assertStringContainsString('<html dir="ltr" lang="en"><head><meta charset="utf-8">', $html);
    }

    public function test_views_are_rendered(): void
    {
        $html = Printly::view('invoice', ['number' => 'INV-7', 'customer' => 'حسين'])->toHtml();

        $this->assertStringContainsString('<h1>فاتورة INV-7</h1>', $html);
        $this->assertStringContainsString('العميل: حسين', $html);
    }

    public function test_colors_and_fonts_reach_the_stylesheet(): void
    {
        $html = Printly::html('<p>نص</p>')
            ->registerFont('Cairo', $this->fixture('Test-Regular.ttf'))
            ->font('Cairo', 14)
            ->color('1A73E8')
            ->background([255, 251, 235])
            ->css('p{margin:0}')
            ->toHtml();

        $this->assertStringContainsString("@font-face{font-family:'Cairo'", $html);
        $this->assertStringContainsString("font-family:'Cairo','Tahoma','Arial',sans-serif;font-size:14pt;color:#1a73e8;", $html);
        $this->assertStringContainsString('@page{background-color:#fffbeb;}', $html);
        $this->assertStringContainsString('<style>p{margin:0}</style>', $html);
    }

    public function test_only_fonts_the_document_uses_are_embedded(): void
    {
        Printly::registerFont('Cairo', $this->fixture('Test-Regular.ttf'));
        Printly::registerFont('Amiri', $this->fixture('Test-Bold.ttf'));

        $this->assertStringNotContainsString('@font-face', Printly::html('<p>نص</p>')->toHtml());

        $html = Printly::html('<p style="font-family:Amiri">نص</p>')->toHtml();

        $this->assertStringContainsString("@font-face{font-family:'Amiri'", $html);
        $this->assertStringNotContainsString("font-family:'Cairo'", $html);
    }

    public function test_fonts_registered_on_one_pdf_do_not_leak_into_others(): void
    {
        Printly::html('x')->registerFont('Cairo', $this->fixture('Test-Regular.ttf'));

        $this->assertStringNotContainsString('@font-face', Printly::html('x')->font('Cairo')->toHtml());
    }

    public function test_fonts_from_config_are_available(): void
    {
        config(['printly.fonts' => ['Cairo' => ['regular' => $this->fixture('Test-Regular.ttf')]], 'printly.google_fonts' => ['Tajawal']]);
        $this->app->forgetInstance(PrintlyManager::class);
        Printly::clearResolvedInstances();

        $html = Printly::html('<p style="font-family:Tajawal">x</p>')->font('Cairo')->toHtml();

        $this->assertStringContainsString("@font-face{font-family:'Cairo'", $html);
        $this->assertStringContainsString('fonts.googleapis.com/css2?family=Tajawal:wght@400;700', $html);
    }

    public function test_invalid_colors_are_rejected(): void
    {
        $this->expectException(InvalidColor::class);

        Printly::html('x')->color('red;}*{display:none');
    }

    public function test_page_setup_is_passed_to_the_driver(): void
    {
        $options = Printly::html('x')->format('a5')->landscape()->margins(10, '1in')->scale(0.8)->pages('1-2')->options();

        $this->assertEqualsWithDelta(148 / 25.4, $options->paperWidth, 0.0001);
        $this->assertEqualsWithDelta(210 / 25.4, $options->paperHeight, 0.0001);
        $this->assertTrue($options->landscape);
        $this->assertEqualsWithDelta(10 / 25.4, $options->marginTop, 0.0001);
        $this->assertEqualsWithDelta(1.0, $options->marginRight, 0.0001);
        $this->assertEqualsWithDelta(10 / 25.4, $options->marginBottom, 0.0001);
        $this->assertEqualsWithDelta(1.0, $options->marginLeft, 0.0001);
        $this->assertSame(0.8, $options->scale);
        $this->assertSame('1-2', $options->pageRanges);
        $this->assertFalse($options->hasHeaderOrFooter());
    }

    public function test_defaults_come_from_config(): void
    {
        config(['printly.defaults' => ['format' => 'Letter', 'orientation' => 'landscape', 'margins' => [10, 20, 30, 40], 'direction' => 'rtl', 'color' => '#333']]);
        $this->app->forgetInstance(PrintlyManager::class);
        Printly::clearResolvedInstances();

        $pdf = Printly::html('x');
        $options = $pdf->options();

        $this->assertEqualsWithDelta(8.5, $options->paperWidth, 0.0001);
        $this->assertTrue($options->landscape);
        $this->assertEqualsWithDelta(40 / 25.4, $options->marginLeft, 0.0001);
        $this->assertStringContainsString('dir="rtl"', $pdf->toHtml());
        $this->assertStringContainsString('color:#333', $pdf->toHtml());
    }

    public function test_unknown_formats_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Printly::html('x')->format('B9');
    }

    public function test_header_and_footer_templates(): void
    {
        $pdf = Printly::html('x')
            ->rtl()
            ->registerFont('Cairo', ['bold' => $this->fixture('Test-Bold.ttf')])
            ->header('<b style="font-family:Cairo">شركة</b>')
            ->pageNumbers('صفحة {page} من {pages}');

        $options = $pdf->options();

        $this->assertTrue($options->hasHeaderOrFooter());
        $this->assertStringContainsString('direction:rtl', $options->headerHtml);
        $this->assertStringContainsString("@font-face{font-family:'Cairo'", $options->headerHtml);
        $this->assertStringContainsString('صفحة <span class="pageNumber"></span> من <span class="totalPages"></span>', $options->footerHtml);

        // The page itself must load the font, or Chrome will not use it in the header.
        $this->assertStringContainsString("<span style=\"font-family:'Cairo';font-weight:700;font-style:normal;\">.</span>", $pdf->toHtml());
    }

    public function test_a_lone_footer_suppresses_chromes_default_header(): void
    {
        $options = Printly::html('x')->footer('{page}')->options();

        $this->assertSame('<span></span>', $options->headerHtml);
    }

    public function test_it_renders_through_the_driver(): void
    {
        $fake = Printly::fake();

        $content = Printly::html('<h1>فاتورة</h1>')->content();

        $this->assertStringStartsWith('%PDF', $content);
        $fake->assertRenderedCount(1)
            ->assertSee('فاتورة')
            ->assertRendered(fn (string $html, RenderOptions $options) => ! $options->landscape);
    }

    public function test_download_and_inline_responses(): void
    {
        Printly::fake();

        $download = Printly::html('x')->download('فاتورة رقم 5');
        $inline = Printly::html('x')->toResponse(request());

        $this->assertSame('application/pdf', $download->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment; filename="fator rkm 5.pdf"; filename*=utf-8\'\'%D9%81', $download->headers->get('Content-Disposition'));
        $this->assertSame('inline; filename=document.pdf', $inline->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $inline->getContent());
    }

    public function test_it_saves_to_a_path_and_to_a_disk(): void
    {
        Printly::fake();
        Storage::fake('reports');
        $path = sys_get_temp_dir().'/printly-test-'.uniqid().'/nested/out.pdf';

        Printly::html('x')->save($path)->save('2026/out.pdf', 'reports');

        $this->assertStringStartsWith('%PDF', file_get_contents($path));
        Storage::disk('reports')->assertExists('2026/out.pdf');

        unlink($path);
        rmdir(dirname($path));
        rmdir(dirname($path, 2));
    }

    public function test_conditional_configuration(): void
    {
        $html = Printly::html('x')->when(true, fn ($pdf) => $pdf->rtl())->toHtml();

        $this->assertStringContainsString('dir="rtl"', $html);
    }
}

<?php

namespace g4t\Printly\Tests;

use g4t\Printly\Exceptions\InvalidColor;
use g4t\Printly\Facades\Printly;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

class DocumentTest extends TestCase
{
    public function test_it_builds_a_document_without_html(): void
    {
        $html = Printly::make()
            ->rtl()
            ->accent('#0f766e')
            ->heading('فاتورة ضريبية')
            ->heading('التفاصيل', level: 2, color: 'tomato', align: 'center')
            ->text("سطر أول\nسطر ثاني", color: '#b91c1c', size: 14, bold: true)
            ->list(['أول', 'ثاني'], ordered: true)
            ->divider('#ccc')
            ->space(10)
            ->pageBreak()
            ->toHtml();

        $this->assertStringContainsString('<div class="pdf-doc" style="--pdf-accent:#0f766e;">', $html);
        $this->assertStringContainsString('<h1>فاتورة ضريبية</h1>', $html);
        $this->assertStringContainsString('<h2 style="color:tomato;text-align:center;">التفاصيل</h2>', $html);
        $this->assertStringContainsString('<p style="color:#b91c1c;font-size:14pt;font-weight:700;">سطر أول<br>'."\n".'سطر ثاني</p>', $html);
        $this->assertStringContainsString('<ol><li>أول</li><li>ثاني</li></ol>', $html);
        $this->assertStringContainsString('<hr style="border-color:#ccc;">', $html);
        $this->assertStringContainsString('<div style="height:10mm;"></div>', $html);
        $this->assertStringContainsString('<div class="pdf-break"></div>', $html);
        $this->assertStringContainsString('.pdf-doc{--pdf-accent:#2563eb;', $html);
    }

    public function test_text_is_escaped_unless_htmlable(): void
    {
        $html = Printly::make()
            ->text('<script>alert(1)</script>')
            ->text(new HtmlString('<strong>مهم</strong>'))
            ->raw('<section>raw</section>')
            ->toHtml();

        $this->assertStringContainsString('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', $html);
        $this->assertStringContainsString('<p><strong>مهم</strong></p>', $html);
        $this->assertStringContainsString('<section>raw</section>', $html);
    }

    public function test_tables(): void
    {
        $html = Printly::make()
            ->table(
                ['المنتج', 'السعر'],
                [['قلم', 1500], ['name' => '<b>دفتر</b>', 'price' => 2000]],
                headerBackground: '#0f766e',
                headerColor: 'white',
                striped: '#f0fdfa',
                borderColor: '#99f6e4',
            )
            ->table([], [['a']], striped: false)
            ->details(['رقم الفاتورة' => 'INV-001'])
            ->toHtml();

        $this->assertStringContainsString('<table class="pdf-striped" style="--pdf-border:#99f6e4;--pdf-stripe:#f0fdfa;">', $html);
        $this->assertStringContainsString('<thead><tr><th style="background:#0f766e;color:white;">المنتج</th>', $html);
        $this->assertStringContainsString('<tr><td>قلم</td><td>1500</td></tr>', $html);
        $this->assertStringContainsString('<td>&lt;b&gt;دفتر&lt;/b&gt;</td>', $html);
        $this->assertStringContainsString('<table><tbody><tr><td>a</td></tr></tbody></table>', $html);
        $this->assertStringContainsString('<table class="pdf-details"><tbody><tr><th>رقم الفاتورة</th><td>INV-001</td></tr>', $html);
    }

    public function test_callouts_and_images(): void
    {
        $png = sys_get_temp_dir().'/printly-'.uniqid().'.png';
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        $html = Printly::make()
            ->callout('ملاحظة', background: '#ecfdf5', color: '#065f46', borderColor: '#059669')
            ->image($png, width: 40, align: 'center')
            ->image('https://example.com/logo.png')
            ->toHtml();

        unlink($png);

        $this->assertStringContainsString('<div class="pdf-callout" style="background:#ecfdf5;color:#065f46;border-color:#059669;">ملاحظة</div>', $html);
        $this->assertStringContainsString('<div class="pdf-image" style="text-align:center;"><img src="data:image/png;base64,iVBOR', $html);
        $this->assertStringContainsString('style="width:40mm;"', $html);
        $this->assertStringContainsString('<img src="https://example.com/logo.png" alt="">', $html);
    }

    public function test_user_css_overrides_the_builder_styles(): void
    {
        $html = Printly::make()->css('.pdf-doc h1{color:red}')->heading('x')->toHtml();

        $this->assertGreaterThan(strpos($html, '.pdf-doc h1{font-size:2em;}'), strpos($html, '.pdf-doc h1{color:red}'));
    }

    public function test_invalid_element_colors_are_rejected(): void
    {
        $this->expectException(InvalidColor::class);

        Printly::make()->text('x', color: 'expression(alert(1))');
    }

    public function test_invalid_alignment_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Printly::make()->heading('x', align: 'middle');
    }

    public function test_missing_images_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Printly::make()->image('/nope/logo.png');
    }
}

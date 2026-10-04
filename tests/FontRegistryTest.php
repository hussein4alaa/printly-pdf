<?php

namespace g4t\Printly\Tests;

use g4t\Printly\Exceptions\InvalidFont;
use g4t\Printly\Fonts\FontRegistry;
use PHPUnit\Framework\TestCase;

class FontRegistryTest extends TestCase
{
    private const REGULAR = __DIR__.'/fixtures/Test-Regular.ttf';

    private const BOLD = __DIR__.'/fixtures/Test-Bold.ttf';

    public function test_it_embeds_local_fonts_as_data_uris(): void
    {
        $css = (new FontRegistry)
            ->register('Cairo', ['regular' => self::REGULAR, 'bold' => self::BOLD])
            ->css(['Cairo']);

        $this->assertSame(2, substr_count($css, '@font-face'));
        $this->assertStringContainsString("font-family:'Cairo'", $css);
        $this->assertStringContainsString('data:font/ttf;base64,'.base64_encode('not-a-real-font').") format('truetype')", $css);
        $this->assertStringContainsString('font-weight:700;font-style:normal', $css);
    }

    public function test_it_understands_variants(): void
    {
        $css = (new FontRegistry)
            ->register('Cairo', ['bold_italic' => self::BOLD, 300 => self::REGULAR, '500italic' => self::REGULAR, 'variable' => self::REGULAR])
            ->css(['Cairo']);

        $this->assertStringContainsString('font-weight:700;font-style:italic', $css);
        $this->assertStringContainsString('font-weight:300;font-style:normal', $css);
        $this->assertStringContainsString('font-weight:500;font-style:italic', $css);
        $this->assertStringContainsString('font-weight:100 900;font-style:normal', $css);
    }

    public function test_google_fonts_become_a_stylesheet_link(): void
    {
        $registry = (new FontRegistry)->google('Noto Kufi Arabic', [700, 400])->google('Cairo');

        $this->assertStringContainsString(
            'https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;700&amp;family=Cairo:wght@400;700',
            $registry->links(['Noto Kufi Arabic', 'Cairo']),
        );
        $this->assertSame('', $registry->css(['Cairo']));
    }

    public function test_it_finds_the_families_a_document_uses(): void
    {
        $registry = (new FontRegistry)->register('Cairo', self::REGULAR)->register('Amiri', self::REGULAR);

        $this->assertSame(['Amiri'], $registry->usedIn('<p style="font-family: amiri">نص</p>'));
    }

    public function test_it_rejects_missing_files(): void
    {
        $this->expectException(InvalidFont::class);
        $this->expectExceptionMessage('not found');

        (new FontRegistry)->register('Cairo', '/nope/Cairo.ttf');
    }

    public function test_it_rejects_unsupported_formats(): void
    {
        $this->expectException(InvalidFont::class);
        $this->expectExceptionMessage('Unsupported font file');

        (new FontRegistry)->register('Cairo', __FILE__);
    }

    public function test_it_rejects_unsafe_family_names(): void
    {
        $this->expectException(InvalidFont::class);

        (new FontRegistry)->register("Cairo';}body{display:none", self::REGULAR);
    }

    public function test_it_rejects_unknown_variants(): void
    {
        $this->expectException(InvalidFont::class);

        (new FontRegistry)->register('Cairo', ['heavyish' => self::REGULAR]);
    }
}

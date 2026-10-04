<?php

namespace g4t\Printly\Tests;

use g4t\Printly\Exceptions\InvalidColor;
use g4t\Printly\Support\Color;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    #[DataProvider('validColors')]
    public function test_it_normalizes_valid_colors(string|array $input, string $expected): void
    {
        $this->assertSame($expected, Color::parse($input));
    }

    public static function validColors(): array
    {
        return [
            'hex' => ['#1A73E8', '#1a73e8'],
            'hex without hash' => ['1a73e8', '#1a73e8'],
            'short hex' => ['#fff', '#fff'],
            'hex with alpha' => ['#1a73e880', '#1a73e880'],
            'rgb' => ['rgb(26, 115, 232)', 'rgb(26, 115, 232)'],
            'rgba' => ['RGBA(0,0,0,.5)', 'rgba(0,0,0,.5)'],
            'hsl' => ['hsl(210deg 50% 40% / 80%)', 'hsl(210deg 50% 40% / 80%)'],
            'named' => ['Tomato', 'tomato'],
            'transparent' => ['transparent', 'transparent'],
            'rgb array' => [[26, 115, 232], '#1a73e8'],
            'rgba array' => [[26, 115, 232, 0.5], 'rgba(26, 115, 232, 0.5)'],
        ];
    }

    #[DataProvider('invalidColors')]
    public function test_it_rejects_invalid_colors(string|array $input): void
    {
        $this->expectException(InvalidColor::class);

        Color::parse($input);
    }

    public static function invalidColors(): array
    {
        return [
            'css injection' => ['red;}body{display:none'],
            'url' => ['url(https://example.com)'],
            'unknown name' => ['notacolor'],
            'bad hex length' => ['#12345'],
            'empty' => [''],
            'short array' => [[1, 2]],
            'non numeric array' => [['a', 'b', 'c']],
        ];
    }
}

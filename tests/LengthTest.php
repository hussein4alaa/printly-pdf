<?php

namespace g4t\Printly\Tests;

use g4t\Printly\Support\Length;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LengthTest extends TestCase
{
    public function test_it_converts_lengths_to_inches(): void
    {
        $this->assertEqualsWithDelta(1.0, Length::inches(25.4), 0.0001);
        $this->assertEqualsWithDelta(1.0, Length::inches('2.54cm'), 0.0001);
        $this->assertEqualsWithDelta(1.0, Length::inches('72pt'), 0.0001);
        $this->assertEqualsWithDelta(1.0, Length::inches('96 px'), 0.0001);
        $this->assertEqualsWithDelta(1.5, Length::inches('1.5in'), 0.0001);
        $this->assertEqualsWithDelta(1.0, Length::inches(72, 'pt'), 0.0001);
    }

    public function test_it_formats_css_lengths(): void
    {
        $this->assertSame('15mm', Length::css(15));
        $this->assertSame('12.5pt', Length::css(12.5, 'pt'));
        $this->assertSame('2cm', Length::css('2CM'));
    }

    public function test_it_rejects_invalid_lengths(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Length::css('10em;color:red');
    }
}

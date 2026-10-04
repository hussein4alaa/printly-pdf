<?php

namespace g4t\Printly\Testing;

use Closure;
use g4t\Printly\Contracts\Driver;
use g4t\Printly\RenderOptions;
use PHPUnit\Framework\Assert;

/**
 * Records renders instead of starting Chrome. Returned by Printly::fake().
 */
class PrintlyFake implements Driver
{
    /** @var list<array{html: string, options: RenderOptions}> */
    protected array $rendered = [];

    public function render(string $html, RenderOptions $options): string
    {
        $this->rendered[] = ['html' => $html, 'options' => $options];

        return "%PDF-1.4\n%fake\n%%EOF";
    }

    /**
     * @return list<array{html: string, options: RenderOptions}>
     */
    public function rendered(): array
    {
        return $this->rendered;
    }

    /**
     * Assert a PDF was rendered, optionally one matching fn (string $html, RenderOptions $options): bool.
     */
    public function assertRendered(?Closure $callback = null): static
    {
        $matches = array_filter($this->rendered, fn (array $render) => $callback === null || $callback($render['html'], $render['options']));

        Assert::assertNotEmpty($matches, $callback === null ? 'No PDF was rendered.' : 'No rendered PDF matched the given callback.');

        return $this;
    }

    public function assertRenderedCount(int $count): static
    {
        Assert::assertCount($count, $this->rendered, 'Expected '.$count.' rendered PDF(s), got '.count($this->rendered).'.');

        return $this;
    }

    public function assertNothingRendered(): static
    {
        return $this->assertRenderedCount(0);
    }

    public function assertSee(string $text): static
    {
        return $this->assertRendered(fn (string $html) => str_contains($html, $text) || str_contains($html, e($text)));
    }
}

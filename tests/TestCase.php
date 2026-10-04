<?php

namespace g4t\Printly\Tests;

use g4t\Printly\PrintlyServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PrintlyServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [__DIR__.'/fixtures/views']);
    }

    protected function fixture(string $name): string
    {
        return __DIR__.'/fixtures/'.$name;
    }
}

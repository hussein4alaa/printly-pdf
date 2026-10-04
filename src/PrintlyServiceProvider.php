<?php

namespace g4t\Printly;

use g4t\Printly\Commands\CheckCommand;
use Illuminate\Support\ServiceProvider;

class PrintlyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/printly.php', 'printly');

        $this->app->singleton(PrintlyManager::class, fn ($app) => new PrintlyManager($app, $app['config']->get('printly', [])));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/printly.php' => config_path('printly.php')], 'printly-config');

            $this->commands([CheckCommand::class]);
        }
    }
}

<?php

namespace g4t\Printly;

use Closure;
use g4t\Printly\Contracts\Driver;
use g4t\Printly\Drivers\ChromeDriver;
use g4t\Printly\Fonts\FontDownloader;
use g4t\Printly\Fonts\FontRegistry;
use g4t\Printly\Testing\PrintlyFake;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class PrintlyManager
{
    protected ?Driver $driver = null;

    protected FontRegistry $fonts;

    /** @var array<string, Closure> */
    protected array $customDrivers = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected Container $container, protected array $config = [])
    {
        $this->fonts = new FontRegistry(new FontDownloader(
            $config['font_cache_path'] ?? sys_get_temp_dir().DIRECTORY_SEPARATOR.'printly-fonts',
            (int) ($config['font_download_timeout'] ?? 15),
        ));

        foreach ($config['fonts'] ?? [] as $family => $sources) {
            $this->fonts->register($family, $sources);
        }

        foreach ($config['google_fonts'] ?? [] as $family => $weights) {
            is_int($family) ? $this->fonts->google($weights) : $this->fonts->google($family, (array) $weights);
        }
    }

    /**
     * Start a PDF from a Blade view.
     *
     * @param  array<string, mixed>  $data
     */
    public function view(string $view, array $data = []): Pdf
    {
        return (new Pdf($this))->usingView($view, $data);
    }

    /**
     * Start a PDF from an HTML string (a fragment or a complete document).
     */
    public function html(string $html): Pdf
    {
        return (new Pdf($this))->usingHtml($html);
    }

    /**
     * Start a PDF built element by element, without writing HTML.
     */
    public function make(): Document
    {
        return new Document($this);
    }

    /**
     * Register a font from files or font-file URLs for every PDF.
     *
     * @param  string|array<string|int, string>  $sources
     *
     * @see FontRegistry::register()
     */
    public function registerFont(string $family, string|array $sources): static
    {
        $this->fonts->register($family, $sources);

        return $this;
    }

    /**
     * Make a Google Fonts family available to every PDF.
     *
     * @param  list<int>  $weights
     */
    public function googleFont(string $family, array $weights = [400, 700]): static
    {
        $this->fonts->google($family, $weights);

        return $this;
    }

    public function fonts(): FontRegistry
    {
        return $this->fonts;
    }

    public function driver(): Driver
    {
        return $this->driver ??= $this->resolveDriver($this->config['driver'] ?? 'chrome');
    }

    public function useDriver(Driver $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * Register a custom driver: Printly::extend('gotenberg', fn ($app, $config) => new GotenbergDriver(...)).
     */
    public function extend(string $name, Closure $factory): static
    {
        $this->customDrivers[$name] = $factory;

        return $this;
    }

    /**
     * Replace the driver with a fake that records renders instead of starting Chrome.
     */
    public function fake(): PrintlyFake
    {
        $this->driver = $fake = new PrintlyFake;

        return $fake;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function renderView(string $view, array $data = []): string
    {
        return $this->container->make(ViewFactory::class)->make($view, $data)->render();
    }

    public function disk(string $name): Filesystem
    {
        return $this->container->make(FilesystemFactory::class)->disk($name);
    }

    public function locale(): string
    {
        return $this->container->bound('config')
            ? (string) $this->container->make('config')->get('app.locale', 'en')
            : 'en';
    }

    protected function resolveDriver(string $name): Driver
    {
        if (isset($this->customDrivers[$name])) {
            return $this->customDrivers[$name]($this->container, $this->config);
        }

        return match ($name) {
            'chrome' => new ChromeDriver($this->config['chrome'] ?? []),
            default => throw new InvalidArgumentException("PDF driver [{$name}] is not supported."),
        };
    }
}

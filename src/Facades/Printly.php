<?php

namespace g4t\Printly\Facades;

use g4t\Printly\PrintlyManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \g4t\Printly\Pdf view(string $view, array $data = [])
 * @method static \g4t\Printly\Pdf html(string $html)
 * @method static \g4t\Printly\Document make()
 * @method static \g4t\Printly\PrintlyManager registerFont(string $family, string|array $sources)
 * @method static \g4t\Printly\PrintlyManager googleFont(string $family, array $weights = [400, 700])
 * @method static \g4t\Printly\PrintlyManager useDriver(\g4t\Printly\Contracts\Driver $driver)
 * @method static \g4t\Printly\PrintlyManager extend(string $name, \Closure $factory)
 * @method static \g4t\Printly\Contracts\Driver driver()
 * @method static \g4t\Printly\Testing\PrintlyFake fake()
 *
 * @see PrintlyManager
 */
class Printly extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PrintlyManager::class;
    }
}

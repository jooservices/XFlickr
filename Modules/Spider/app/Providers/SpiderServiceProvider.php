<?php

namespace Modules\Spider\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Spider\Console\Commands\ExpandSpiderFrontierCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SpiderServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Spider';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'spider';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ExpandSpiderFrontierCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}

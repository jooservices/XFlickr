<?php

namespace Modules\Storage\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Storage\Console\Commands\VerifyGooglePhotosConnectionCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class StorageServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Storage';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'storage';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        VerifyGooglePhotosConnectionCommand::class,
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

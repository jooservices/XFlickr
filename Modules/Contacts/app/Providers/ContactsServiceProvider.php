<?php

namespace Modules\Contacts\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Contacts\Console\Commands\ExpandContactFullPassCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ContactsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Contacts';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'contacts';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ExpandContactFullPassCommand::class,
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

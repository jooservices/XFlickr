<?php

namespace Modules\Flickr\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Flickr\Console\Commands\DoctorCommand;
use Modules\Flickr\Console\Commands\FlickrApiAuditCommand;
use Modules\Flickr\Services\FlickrSourceUrlResolver;
use Modules\Storage\Contracts\SourceUrlResolver;
use Nwidart\Modules\Support\ModuleServiceProvider;

class FlickrServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->bind(SourceUrlResolver::class, FlickrSourceUrlResolver::class);
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Flickr';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'flickr';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        DoctorCommand::class,
        FlickrApiAuditCommand::class,
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

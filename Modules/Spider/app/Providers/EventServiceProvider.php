<?php

namespace Modules\Spider\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use JOOservices\XFlickrCrawler\Events\ContactsCrawlCompleted;
use Modules\Spider\Listeners\HandleContactsCrawlCompleted;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        ContactsCrawlCompleted::class => [
            HandleContactsCrawlCompleted::class,
        ],
    ];

    protected static $shouldDiscoverEvents = false;

    protected function configureEmailVerification(): void {}
}

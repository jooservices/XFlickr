<?php

namespace Modules\Contacts\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use JOOservices\XFlickrCrawler\Events\ContactsCrawlCompleted;
use Modules\Contacts\Listeners\HandleContactsCrawlCompletedForFullPass;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        ContactsCrawlCompleted::class => [
            HandleContactsCrawlCompletedForFullPass::class,
        ],
    ];

    protected static $shouldDiscoverEvents = false;

    protected function configureEmailVerification(): void {}
}

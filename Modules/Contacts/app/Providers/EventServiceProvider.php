<?php

namespace Modules\Contacts\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Contacts\Listeners\HandleContactsCrawlCompletedForFullPass;
use Modules\Crawler\Events\ContactsCrawlCompleted;

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

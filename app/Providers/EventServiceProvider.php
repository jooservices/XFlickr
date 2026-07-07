<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\Spider\HandleContactsCrawlCompleted;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use JOOservices\XFlickrCrawler\Events\ContactsCrawlCompleted;

final class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        ContactsCrawlCompleted::class => [
            HandleContactsCrawlCompleted::class,
        ],
    ];
}

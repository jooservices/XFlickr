<?php

declare(strict_types=1);

namespace Modules\Operations\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Crawler\Events\ContactsCrawlCompleted;
use Modules\Crawler\Events\CrawlPageFailed;
use Modules\Crawler\Events\CrawlRunCompleted;
use Modules\Crawler\Events\CrawlRunFailed;
use Modules\Crawler\Events\CrawlRunStarted;
use Modules\Operations\Listeners\BroadcastOperationsBatchUpdated;
use Modules\Operations\Listeners\BroadcastOperationsOverviewFromDomain;
use Modules\Transfer\Events\TransferBatchReconciled;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        TransferBatchReconciled::class => [
            BroadcastOperationsBatchUpdated::class,
        ],
        CrawlRunStarted::class => [
            BroadcastOperationsOverviewFromDomain::class,
        ],
        CrawlRunCompleted::class => [
            BroadcastOperationsOverviewFromDomain::class,
        ],
        CrawlRunFailed::class => [
            BroadcastOperationsOverviewFromDomain::class,
        ],
        ContactsCrawlCompleted::class => [
            BroadcastOperationsOverviewFromDomain::class,
        ],
        CrawlPageFailed::class => [
            BroadcastOperationsOverviewFromDomain::class,
        ],
    ];

    protected static $shouldDiscoverEvents = false;

    protected function configureEmailVerification(): void {}
}

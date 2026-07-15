<?php

declare(strict_types=1);

namespace Modules\Operations\Listeners;

use Modules\Crawler\Events\ContactsCrawlCompleted;
use Modules\Crawler\Events\CrawlPageFailed;
use Modules\Crawler\Events\CrawlRunCompleted;
use Modules\Operations\Services\OperationsBroadcastService;

final class BroadcastOperationsOverviewFromDomain
{
    public function __construct(
        private readonly OperationsBroadcastService $broadcast,
    ) {}

    public function handle(CrawlRunCompleted|ContactsCrawlCompleted|CrawlPageFailed $event): void
    {
        $this->broadcast->broadcastOverviewChanged();
    }
}

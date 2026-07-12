<?php

declare(strict_types=1);

namespace Modules\Contacts\Listeners;

use JOOservices\XFlickrCrawler\Events\ContactsCrawlCompleted;
use Modules\Contacts\Services\ContactFullPassPlannerService;

final class HandleContactsCrawlCompletedForFullPass
{
    public function __construct(
        private readonly ContactFullPassPlannerService $planner,
    ) {}

    public function handle(ContactsCrawlCompleted $event): void
    {
        $this->planner->handleContactsCrawlCompleted($event);
    }
}

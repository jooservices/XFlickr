<?php

declare(strict_types=1);

namespace Modules\Spider\Listeners;

use JOOservices\XFlickrCrawler\Events\ContactsCrawlCompleted;
use Modules\Spider\Services\SpiderPlannerService;

final class HandleContactsCrawlCompleted
{
    public function __construct(
        private readonly SpiderPlannerService $planner,
    ) {}

    public function handle(ContactsCrawlCompleted $event): void
    {
        $this->planner->handleContactsCrawlCompleted($event);
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners\Spider;

use App\Services\Flickr\SpiderPlannerService;
use JOOservices\XFlickrCrawler\Events\ContactsCrawlCompleted;

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

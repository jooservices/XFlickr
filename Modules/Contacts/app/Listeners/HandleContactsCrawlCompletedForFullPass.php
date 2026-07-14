<?php

declare(strict_types=1);

namespace Modules\Contacts\Listeners;

use Modules\Contacts\Services\ContactFullPassPlannerService;
use Modules\Crawler\Events\ContactsCrawlCompleted;

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

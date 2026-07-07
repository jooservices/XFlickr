<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Flickr\SpiderPlannerService;
use Illuminate\Console\Command;

final class ExpandSpiderFrontierCommand extends Command
{
    protected $signature = 'xflickr:spider:expand';

    protected $description = 'Expand active spider runs by queueing crawl targets for pending frontier contacts';

    public function handle(SpiderPlannerService $planner): int
    {
        if (! $planner->isEnabled()) {
            return self::SUCCESS;
        }

        $queued = $planner->expandActiveRuns();

        if ($queued > 0) {
            $this->info("Queued {$queued} spider frontier contact(s) for crawl.");
        }

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Contacts\Console\Commands;

use Illuminate\Console\Command;
use Modules\Contacts\Services\ContactFullPassPlannerService;

final class ExpandContactFullPassCommand extends Command
{
    protected $signature = 'xflickr:contacts:full-pass-expand';

    protected $description = 'Expand active full contact pass runs by queueing catalog crawls for pending frontier contacts';

    public function handle(ContactFullPassPlannerService $planner): int
    {
        $queued = $planner->expandActiveRuns();

        if ($queued > 0) {
            $this->info("Queued {$queued} full-pass frontier contact(s) for crawl.");
        }

        return self::SUCCESS;
    }
}

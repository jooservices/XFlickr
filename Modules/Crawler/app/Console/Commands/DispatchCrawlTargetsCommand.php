<?php

declare(strict_types=1);

namespace Modules\Crawler\Console\Commands;

use Illuminate\Console\Command;
use Modules\Crawler\Services\FlickrSpiderService;

final class DispatchCrawlTargetsCommand extends Command
{
    protected $signature = 'xflickr:crawler:dispatch';

    protected $aliases = ['xflickr:dispatch'];

    protected $description = 'Dispatch due XFlickr crawl targets to the queue';

    public function handle(FlickrSpiderService $spider): int
    {
        $dispatched = $spider->dispatchDueTargets();
        $this->info("Dispatched {$dispatched} crawl target(s).");

        return self::SUCCESS;
    }
}

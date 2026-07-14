<?php

declare(strict_types=1);

namespace Modules\Crawler\Console;

use Illuminate\Console\Command;
use Modules\Crawler\Services\CrawlPruneService;

final class PruneCrawlDataCommand extends Command
{
    protected $signature = 'xflickr:prune {--days=30 : Delete API logs older than this many days} {--targets : Also delete completed crawl targets older than the retention window}';

    protected $description = 'Prune old XFlickr API logs and optionally completed crawl targets';

    public function handle(CrawlPruneService $prune): int
    {
        $days = max(1, (int) $this->option('days'));

        $logsDeleted = $prune->pruneApiLogsOlderThanDays($days);
        $this->info("Pruned {$logsDeleted} API log row(s) older than {$days} day(s).");

        if ($this->option('targets')) {
            $targetsDeleted = $prune->pruneCompletedTargetsOlderThanDays($days);
            $this->info("Pruned {$targetsDeleted} completed crawl target row(s).");
        }

        return self::SUCCESS;
    }
}

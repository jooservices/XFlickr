<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use Modules\Crawler\Repositories\ApiLogRepository;
use Modules\Crawler\Repositories\CrawlTargetRepository;

final class CrawlPruneService
{
    public function __construct(
        private readonly ApiLogRepository $apiLogs,
        private readonly CrawlTargetRepository $targets,
    ) {}

    public function pruneApiLogsOlderThanDays(int $days): int
    {
        $days = max(1, $days);
        $cutoff = now()->subDays($days);

        return $this->apiLogs->deleteOlderThan($cutoff);
    }

    public function pruneCompletedTargetsOlderThanDays(int $days): int
    {
        $days = max(1, $days);
        $cutoff = now()->subDays($days);

        return $this->targets->deleteCompletedOlderThan($cutoff);
    }
}

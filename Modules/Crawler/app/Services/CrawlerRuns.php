<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Repositories\CrawlRunRepository;

final class CrawlerRuns
{
    public function __construct(
        private readonly CrawlRunRepository $crawlRuns,
    ) {}

    /**
     * @return Collection<int, CrawlRun>
     */
    public function activeForConnection(string $connectionKey): Collection
    {
        return $this->crawlRuns->activeForConnection($connectionKey);
    }

    /**
     * @return Collection<int, CrawlRun>
     */
    public function recentForConnection(string $connectionKey, int $limit = 20): Collection
    {
        return $this->crawlRuns->recentForConnection($connectionKey, $limit);
    }
}

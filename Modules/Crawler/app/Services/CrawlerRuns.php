<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Repositories\CrawlRunRepository;
use Modules\Crawler\Repositories\SubjectContactRepository;

final class CrawlerRuns
{
    public function __construct(
        private readonly CrawlRunRepository $crawlRuns,
        private readonly SubjectContactRepository $subjectContacts,
    ) {}

    /**
     * @return Collection<int, CrawlRun>
     */
    public function activeForConnection(string $connectionKey): Collection
    {
        return $this->crawlRuns->activeForConnection($connectionKey);
    }

    /** @param callable(list<string>): void $callback */
    public function chunkDiscoveredContacts(int $crawlRunId, int $size, callable $callback): void
    {
        $this->subjectContacts->chunkDiscoveredForCrawlRun($crawlRunId, $size, $callback);
    }

    /**
     * @return Collection<int, CrawlRun>
     */
    public function recentForConnection(string $connectionKey, int $limit = 20): Collection
    {
        return $this->crawlRuns->recentForConnection($connectionKey, $limit);
    }
}

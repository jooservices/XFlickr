<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use Illuminate\Support\Collection;
use JOOservices\XFlickrCrawler\Enums\CrawlStatus;
use JOOservices\XFlickrCrawler\Enums\TaskType;
use JOOservices\XFlickrCrawler\Models\CrawlTarget;

final class CrawlTargetQueryRepository
{
    public function countPendingForConnection(string $connectionKey): int
    {
        return CrawlTarget::query()
            ->whereHas('crawlRun', fn ($q) => $q->where('connection_key', $connectionKey))
            ->where('status', 'pending')
            ->count();
    }

    /**
     * @param  list<string>  $connectionKeys
     */
    public function countPendingForConnections(array $connectionKeys): int
    {
        if ($connectionKeys === []) {
            return 0;
        }

        return CrawlTarget::query()
            ->whereHas('crawlRun', fn ($q) => $q->whereIn('connection_key', $connectionKeys))
            ->where('status', 'pending')
            ->count();
    }

    /**
     * @param  list<int>  $runIds
     * @return Collection<int, Collection<int, CrawlTarget>>
     */
    public function groupedByRunIds(array $runIds): Collection
    {
        if ($runIds === []) {
            return collect();
        }

        return CrawlTarget::query()
            ->whereIn('xflickr_crawl_run_id', $runIds)
            ->get(['xflickr_crawl_run_id', 'task_type', 'subject_nsid', 'status', 'last_result_count', 'page'])
            ->groupBy('xflickr_crawl_run_id');
    }

    /**
     * @param  list<TaskType>  $taskTypes
     */
    public function sumCompletedResults(int $runId, array $taskTypes, string $subjectNsid): int
    {
        return (int) CrawlTarget::query()
            ->where('xflickr_crawl_run_id', $runId)
            ->whereIn('task_type', array_map(fn (TaskType $type): string => $type->value, $taskTypes))
            ->where('subject_nsid', $subjectNsid)
            ->where('status', CrawlStatus::Completed)
            ->sum('last_result_count');
    }
}

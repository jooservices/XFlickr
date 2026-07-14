<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use Illuminate\Support\Collection;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;

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
     * @param  list<string>  $connectionKeys
     * @return array<string, int>
     */
    public function countPendingGroupedByConnection(array $connectionKeys): array
    {
        if ($connectionKeys === []) {
            return [];
        }

        $runsTable = (new CrawlRun)->getTable();
        $targetsTable = (new CrawlTarget)->getTable();

        return CrawlTarget::query()
            ->join($runsTable, "{$targetsTable}.xflickr_crawl_run_id", '=', "{$runsTable}.id")
            ->whereIn("{$runsTable}.connection_key", $connectionKeys)
            ->where("{$targetsTable}.status", 'pending')
            ->selectRaw("{$runsTable}.connection_key, count(*) as aggregate")
            ->groupBy("{$runsTable}.connection_key")
            ->pluck('aggregate', 'connection_key')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  list<int>  $runIds
     * @return Collection<int, Collection<int, CrawlTarget>>
     */
    /**
     * @param  list<int>  $runIds
     * @return Collection<int, Collection<int, CrawlTarget>>
     */
    public function groupedByRunIds(array $runIds): Collection
    {
        if ($runIds === []) {
            return collect();
        }

        /** @var Collection<int, Collection<int, CrawlTarget>> $grouped */
        $grouped = CrawlTarget::query()
            ->whereIn('xflickr_crawl_run_id', $runIds)
            ->get(['xflickr_crawl_run_id', 'task_type', 'subject_nsid', 'status', 'last_result_count', 'page'])
            ->groupBy('xflickr_crawl_run_id');

        return $grouped;
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

<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use Illuminate\Support\Collection;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;

final class CrawlTargetQueryRepository
{
    public function countPendingForConnection(string $connectionKey): int
    {
        return CrawlTarget::query()
            ->whereHas('crawlRun', fn ($q) => $q->where('connection_key', $connectionKey))
            ->pending()
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
            ->pending()
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
            ->completed()
            ->sum('last_result_count');
    }

    /**
     * Status × task_type counts for running crawl runs only.
     *
     * @param  list<string>  $connectionKeys
     * @return list<array{connection_key: string, crawl_run_id: int, status: string, task_type: string, count: int}>
     */
    public function statusTaskTypeCountsForRunningRuns(array $connectionKeys): array
    {
        if ($connectionKeys === []) {
            return [];
        }

        $runsTable = (new CrawlRun)->getTable();
        $targetsTable = (new CrawlTarget)->getTable();

        $rows = CrawlTarget::query()
            ->join($runsTable, "{$targetsTable}.xflickr_crawl_run_id", '=', "{$runsTable}.id")
            ->whereIn("{$runsTable}.connection_key", $connectionKeys)
            ->where("{$runsTable}.status", 'running')
            ->selectRaw("{$runsTable}.connection_key as connection_key, {$targetsTable}.xflickr_crawl_run_id as crawl_run_id, {$targetsTable}.status as status, {$targetsTable}.task_type as task_type, count(*) as aggregate")
            ->groupBy(
                "{$runsTable}.connection_key",
                "{$targetsTable}.xflickr_crawl_run_id",
                "{$targetsTable}.status",
                "{$targetsTable}.task_type",
            )
            ->toBase()
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'connection_key' => (string) $row->connection_key,
                'crawl_run_id' => (int) $row->crawl_run_id,
                'status' => (string) $row->status,
                'task_type' => (string) $row->task_type,
                'count' => (int) $row->aggregate,
            ];
        }

        return $out;
    }
}

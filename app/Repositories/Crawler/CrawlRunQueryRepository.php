<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Models\CrawlRun;

final class CrawlRunQueryRepository
{
    public function countByConnectionAndStatus(string $connectionKey, string $status): int
    {
        return CrawlRun::query()
            ->where('connection_key', $connectionKey)
            ->where('status', $status)
            ->count();
    }

    /**
     * @param  list<string>  $connectionKeys
     */
    public function countByConnectionsAndStatus(array $connectionKeys, string $status): int
    {
        if ($connectionKeys === []) {
            return 0;
        }

        return CrawlRun::query()
            ->whereIn('connection_key', $connectionKeys)
            ->where('status', $status)
            ->count();
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array<string, array{running: int, completed: int, failed: int}>
     */
    public function statusCountsGroupedByConnection(array $connectionKeys): array
    {
        if ($connectionKeys === []) {
            return [];
        }

        $rows = CrawlRun::query()
            ->whereIn('connection_key', $connectionKeys)
            ->selectRaw('connection_key, status, count(*) as aggregate')
            ->groupBy('connection_key', 'status')
            ->get();

        /** @var array<string, array{running: int, completed: int, failed: int}> $grouped */
        $grouped = [];

        foreach ($connectionKeys as $key) {
            $grouped[$key] = ['running' => 0, 'completed' => 0, 'failed' => 0];
        }

        foreach ($rows as $row) {
            $key = (string) $row->connection_key;
            $status = $row->status instanceof CrawlRunStatus ? $row->status->value : (string) $row->status;
            if (isset($grouped[$key][$status])) {
                $grouped[$key][$status] = (int) ($row->getAttribute('aggregate') ?? 0);
            }
        }

        return $grouped;
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array<string, CrawlRun>
     */
    public function latestRunsByConnection(array $connectionKeys): array
    {
        if ($connectionKeys === []) {
            return [];
        }

        $runs = CrawlRun::query()
            ->whereIn('connection_key', $connectionKeys)
            ->orderByDesc('id')
            ->get();

        $latest = [];
        foreach ($runs as $run) {
            $key = (string) $run->connection_key;
            if (! isset($latest[$key])) {
                $latest[$key] = $run;
            }
        }

        return $latest;
    }

    /**
     * @return array{running: int, completed: int, failed: int}
     */
    public function statusCountsForConnection(string $connectionKey): array
    {
        return [
            'running' => $this->countByConnectionAndStatus($connectionKey, 'running'),
            'completed' => $this->countByConnectionAndStatus($connectionKey, 'completed'),
            'failed' => $this->countByConnectionAndStatus($connectionKey, 'failed'),
        ];
    }

    public function latestForConnection(string $connectionKey): ?CrawlRun
    {
        return CrawlRun::query()
            ->where('connection_key', $connectionKey)
            ->orderByDesc('id')
            ->first();
    }

    public function findLatestCompleted(
        string $connectionKey,
        string $subjectNsid,
        string $crawlType,
    ): ?CrawlRun {
        return CrawlRun::query()
            ->where('connection_key', $connectionKey)
            ->where('subject_nsid', $subjectNsid)
            ->where('crawl_type', $crawlType)
            ->where('status', CrawlRunStatus::Completed)
            ->orderByDesc('id')
            ->first(['id', 'photos_discovered']);
    }

    /**
     * @param  list<string>  $contactNsids
     * @param  list<string>  $crawlTypes
     * @return Collection<int, CrawlRun>
     */
    public function listForContacts(
        string $connectionKey,
        array $contactNsids,
        array $crawlTypes,
        array $statuses,
    ): Collection {
        if ($contactNsids === []) {
            return collect();
        }

        return CrawlRun::query()
            ->where('connection_key', $connectionKey)
            ->whereIn('subject_nsid', $contactNsids)
            ->whereIn('crawl_type', $crawlTypes)
            ->whereIn('status', $statuses)
            ->orderByDesc('id')
            ->get(['id', 'subject_nsid', 'crawl_type', 'status']);
    }

    /**
     * @return Builder<CrawlRun>
     */
    public function queryForConnection(string $connectionKey): Builder
    {
        return CrawlRun::query()->where('connection_key', $connectionKey);
    }

    public function paginateQuery(Builder $query, int $perPage, int $page): LengthAwarePaginator
    {
        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}

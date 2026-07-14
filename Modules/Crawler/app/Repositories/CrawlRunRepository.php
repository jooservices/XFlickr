<?php

declare(strict_types=1);

namespace Modules\Crawler\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Models\CrawlRun;

final class CrawlRunRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CrawlRun
    {
        return CrawlRun::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CrawlRun $run, array $attributes): CrawlRun
    {
        $run->update($attributes);

        return $run->fresh(['targets']) ?? $run;
    }

    public function incrementApiCalls(CrawlRun $run): void
    {
        CrawlRun::query()
            ->whereKey($run->id)
            ->increment('api_calls');
    }

    /**
     * @return Collection<int, CrawlRun>
     */
    public function activeForConnection(string $connectionKey): Collection
    {
        return CrawlRun::query()
            ->where('connection_key', $connectionKey)
            ->where('status', CrawlRunStatus::Running)
            ->with('targets')
            ->orderByDesc('started_at')
            ->get();
    }

    /**
     * @return Collection<int, CrawlRun>
     */
    public function recentForConnection(string $connectionKey, int $limit = 20): Collection
    {
        return CrawlRun::query()
            ->where('connection_key', $connectionKey)
            ->with('targets')
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Crawler\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Models\CrawlRun;

final class CrawlRunRepository
{
    public function findRunningContactsCrawl(string $connectionKey, ?string $subjectNsid): ?CrawlRun
    {
        $query = CrawlRun::query()
            ->forConnection($connectionKey)
            ->where('crawl_type', CrawlType::Contacts->value)
            ->running()
            ->orderByDesc('id');

        if ($subjectNsid === null) {
            $query->where(function ($builder) use ($connectionKey): void {
                $builder
                    ->whereNull('subject_nsid')
                    ->orWhere('subject_nsid', $connectionKey);
            });
        } else {
            $query->where('subject_nsid', $subjectNsid);
        }

        $run = $query->first();

        return $run instanceof CrawlRun ? $run : null;
    }

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

    public function incrementDiscoveryCounter(CrawlRun $run, string $column, int $by): void
    {
        if ($by === 0) {
            return;
        }

        CrawlRun::query()->whereKey($run->id)->increment($column, $by);
    }

    public function incrementFailedTargets(CrawlRun $run): void
    {
        CrawlRun::query()->whereKey($run->id)->increment('targets_failed');
    }

    /**
     * @return Collection<int, CrawlRun>
     */
    public function activeForConnection(string $connectionKey): Collection
    {
        return CrawlRun::query()
            ->forConnection($connectionKey)
            ->running()
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
            ->forConnection($connectionKey)
            ->with('targets')
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }
}

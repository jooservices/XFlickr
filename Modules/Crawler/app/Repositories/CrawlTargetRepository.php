<?php

declare(strict_types=1);

namespace Modules\Crawler\Repositories;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\CrawlTarget;

final class CrawlTargetRepository
{
    public function findWithRun(int $id): ?CrawlTarget
    {
        return CrawlTarget::query()
            ->with('crawlRun')
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CrawlTarget $target, array $attributes): CrawlTarget
    {
        $target->update($attributes);

        return $target->fresh(['crawlRun']) ?? $target;
    }

    /**
     * Mark a target Processing for job execution, or null if missing/already completed.
     */
    public function claimForProcessing(int $crawlTargetId): ?CrawlTarget
    {
        $target = $this->findWithRun($crawlTargetId);
        if ($target === null || $target->status === CrawlStatus::Completed) {
            return null;
        }

        return $this->update($target, [
            'status' => CrawlStatus::Processing,
            'locked_until' => now()->addMinutes(15),
        ]);
    }

    /**
     * @param  array<string, mixed>  $lookup
     * @param  array<string, mixed>  $attributes
     */
    public function firstOrCreate(array $lookup, array $attributes): CrawlTarget
    {
        return CrawlTarget::query()->firstOrCreate($lookup, $attributes);
    }

    public function recoverStalled(CarbonInterface $cutoff): int
    {
        return CrawlTarget::query()
            ->processing()
            ->where('updated_at', '<', $cutoff)
            ->update([
                'status' => CrawlStatus::Pending,
                'locked_until' => null,
                'next_run_at' => now(),
            ]);
    }

    /**
     * @param  callable(Collection<int, CrawlTarget>): (bool|null)  $callback
     */
    public function chunkDueForDispatch(int $chunkSize, CarbonImmutable $now, callable $callback): void
    {
        $this->dueForDispatchQuery($now)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->chunkById($chunkSize, $callback);
    }

    /**
     * @param  callable(Collection<int, CrawlTarget>): void  $callback
     */
    public function chunkPendingForRun(int $runId, int $chunkSize, CarbonImmutable $now, callable $callback): void
    {
        CrawlTarget::query()
            ->where('xflickr_crawl_run_id', $runId)
            ->pending()
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('locked_until')->orWhere('locked_until', '<=', $now);
            })
            ->orderBy('id')
            ->chunkById($chunkSize, $callback);
    }

    public function tryQueueLock(CrawlTarget $target): bool
    {
        $locked = CrawlTarget::query()
            ->whereKey($target->id)
            ->whereIn('status', [CrawlStatus::Pending, CrawlStatus::Queued])
            ->update([
                'status' => CrawlStatus::Queued,
                'locked_until' => now()->addMinutes(15),
            ]);

        return $locked > 0;
    }

    public function hasUnresolvedForRun(int $runId): bool
    {
        return CrawlTarget::query()
            ->where('xflickr_crawl_run_id', $runId)
            ->whereNotIn('status', [CrawlStatus::Completed, CrawlStatus::Skipped, CrawlStatus::Failed])
            ->exists();
    }

    public function countCompletedForRun(int $runId): int
    {
        return CrawlTarget::query()
            ->where('xflickr_crawl_run_id', $runId)
            ->completed()
            ->count();
    }

    public function firstFailedForRun(int $runId): ?CrawlTarget
    {
        return CrawlTarget::query()
            ->where('xflickr_crawl_run_id', $runId)
            ->failed()
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  list<TaskType>  $taskTypes
     */
    public function sumCompletedResultCount(int $runId, array $taskTypes): int
    {
        return (int) CrawlTarget::query()
            ->where('xflickr_crawl_run_id', $runId)
            ->completed()
            ->whereIn('task_type', $taskTypes)
            ->sum('last_result_count');
    }

    public function deleteCompletedOlderThan(CarbonInterface $cutoff): int
    {
        return CrawlTarget::query()
            ->completed()
            ->where('last_crawled_at', '<', $cutoff)
            ->delete();
    }

    /**
     * @return Builder<CrawlTarget>
     */
    private function dueForDispatchQuery(CarbonImmutable $now): Builder
    {
        return CrawlTarget::query()
            ->whereIn('status', [CrawlStatus::Pending, CrawlStatus::Queued])
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('locked_until')->orWhere('locked_until', '<=', $now);
            });
    }
}

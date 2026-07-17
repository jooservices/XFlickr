<?php

declare(strict_types=1);

namespace Modules\Crawler\Repositories;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Crawler\DTO\CrawlTaskSpec;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Support\XFlickrConfig;

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

    /** Mark a claimable target Processing, returning its fencing token. */
    public function claimForProcessing(int $crawlTargetId): ?CrawlTarget
    {
        $token = (string) Str::uuid();
        $expiresAt = now()->addMinutes(XFlickrConfig::crawlInt('stall_minutes', 15));

        $claimed = CrawlTarget::query()
            ->whereKey($crawlTargetId)
            ->whereIn('status', [CrawlStatus::Pending, CrawlStatus::Queued])
            ->update([
                'status' => CrawlStatus::Processing,
                'claim_token' => $token,
                'claim_expires_at' => $expiresAt,
                'locked_until' => $expiresAt,
            ]);

        if ($claimed !== 1) {
            return null;
        }

        return $this->findWithRun($crawlTargetId);
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
                'claim_token' => null,
                'claim_expires_at' => null,
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

    public function completeClaimed(CrawlTarget $target, string $claimToken, int $resultCount): bool
    {
        return CrawlTarget::query()
            ->whereKey($target->id)
            ->where('status', CrawlStatus::Processing)
            ->where('claim_token', $claimToken)
            ->update([
                'status' => CrawlStatus::Completed,
                'last_result_count' => $resultCount,
                'last_crawled_at' => now(),
                'locked_until' => null,
                'claim_token' => null,
                'claim_expires_at' => null,
                'failed_reason' => null,
            ]) === 1;
    }

    public function releaseClaimed(CrawlTarget $target, string $claimToken, int $seconds): bool
    {
        return CrawlTarget::query()
            ->whereKey($target->id)
            ->where('status', CrawlStatus::Processing)
            ->where('claim_token', $claimToken)
            ->update([
                'status' => CrawlStatus::Pending,
                'next_run_at' => now()->addSeconds($seconds),
                'locked_until' => null,
                'claim_token' => null,
                'claim_expires_at' => null,
                'retry_count' => $target->retry_count + 1,
            ]) === 1;
    }

    public function failClaimed(CrawlTarget $target, string $claimToken, string $reason): bool
    {
        return CrawlTarget::query()
            ->whereKey($target->id)
            ->where('status', CrawlStatus::Processing)
            ->where('claim_token', $claimToken)
            ->update([
                'status' => CrawlStatus::Failed,
                'failed_reason' => $reason,
                'locked_until' => null,
                'claim_token' => null,
                'claim_expires_at' => null,
                'last_crawled_at' => now(),
            ]) === 1;
    }

    public function countFailedForRun(int $runId): int
    {
        return CrawlTarget::query()->where('xflickr_crawl_run_id', $runId)->failed()->count();
    }

    /** @param list<CrawlTaskSpec> $specs */
    public function insertPendingSpecs(CrawlRun $run, array $specs): int
    {
        if ($specs === []) {
            return 0;
        }

        $now = now()->toDateTimeString();
        $rows = array_map(static fn (CrawlTaskSpec $spec): array => [
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => $spec->taskType->value,
            'subject_nsid' => $spec->subjectNsid ?? '',
            'subject_id' => $spec->subjectId ?? '',
            'page' => $spec->page,
            'status' => CrawlStatus::Pending->value,
            'priority' => $spec->priority,
            'next_run_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $specs);

        $inserted = 0;
        foreach (array_chunk($rows, (int) config('xflickr-crawler.bulk.chunk_size', 250)) as $chunk) {
            $inserted += DB::table((new CrawlTarget)->getTable())->insertOrIgnore($chunk);
        }

        return $inserted;
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

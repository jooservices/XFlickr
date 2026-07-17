<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\Crawler\DTO\CrawlTaskSpec;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Events\ContactsCrawlCompleted;
use Modules\Crawler\Events\CrawlRunCompleted;
use Modules\Crawler\Events\CrawlRunFailed;
use Modules\Crawler\Events\CrawlRunStarted;
use Modules\Crawler\Jobs\CrawlTargetJobFactory;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Repositories\CrawlRunRepository;
use Modules\Crawler\Repositories\CrawlTargetRepository;
use Modules\Crawler\Support\CrawlStall;
use Modules\Crawler\Support\XFlickrConfig;

final class FlickrSpiderService
{
    public function __construct(
        private readonly CrawlTargetJobFactory $jobFactory,
        private readonly CrawlTargetRepository $targets,
        private readonly CrawlRunRepository $crawlRuns,
        private readonly CrawlerObservability $observability,
    ) {}

    public function dispatchDueTargets(): int
    {
        if (XFlickrConfig::globalPause()) {
            return 0;
        }

        $this->recoverStalledTargets();

        $limit = XFlickrConfig::dispatchLimit();
        $now = CarbonImmutable::now();
        $dispatched = 0;

        $this->targets->chunkDueForDispatch(100, $now, function (Collection $chunk) use (&$dispatched, $limit): bool {
            foreach ($chunk as $target) {
                if ($limit > 0 && $dispatched >= $limit) {
                    return false;
                }

                if ($this->dispatchTarget($target)) {
                    $dispatched++;
                }
            }

            return ! ($limit > 0 && $dispatched >= $limit);
        });

        return $dispatched;
    }

    public function recoverStalledTargets(): int
    {
        $count = $this->targets->recoverStalled(CrawlStall::cutoff());
        $this->observability->stalledRecovered($count);

        return $count;
    }

    public function enqueueTarget(
        CrawlRun $run,
        TaskType $taskType,
        ?string $subjectNsid,
        ?string $subjectId,
        int $page,
        int $priority = 0,
    ): CrawlTarget {
        $subjectNsid = $subjectNsid ?? '';
        $subjectId = $subjectId ?? '';

        return $this->targets->firstOrCreate(
            [
                'xflickr_crawl_run_id' => $run->id,
                'task_type' => $taskType,
                'subject_nsid' => $subjectNsid,
                'subject_id' => $subjectId,
                'page' => $page,
            ],
            [
                'status' => CrawlStatus::Pending,
                'priority' => $priority,
                'next_run_at' => now(),
            ],
        );
    }

    /**
     * @param  list<CrawlTaskSpec>  $specs
     */
    public function enqueueSpecs(CrawlRun $run, array $specs): int
    {
        return $this->targets->insertPendingSpecs($run, $specs);
    }

    public function createRun(
        string $connectionKey,
        CrawlType $crawlType,
        ?string $subjectNsid = null,
        ?int $spiderRunId = null,
        ?int $spiderFrontierItemId = null,
    ): CrawlRun {
        $run = $this->crawlRuns->create([
            'connection_key' => $connectionKey,
            'crawl_type' => $crawlType->value,
            'subject_nsid' => $subjectNsid,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
            'spider_run_id' => $spiderRunId,
            'spider_frontier_item_id' => $spiderFrontierItemId,
        ]);

        $this->observability->runStarted($run);
        event(new CrawlRunStarted($run));

        return $run;
    }

    public function maybeCompleteRun(CrawlRun $run): void
    {
        if ($this->targets->hasUnresolvedForRun($run->id) || $run->status !== CrawlRunStatus::Running) {
            return;
        }

        $completedCount = $this->targets->countCompletedForRun($run->id);
        $failedTarget = $this->targets->firstFailedForRun($run->id);

        if ($completedCount === 0 && $failedTarget !== null) {
            $reason = $failedTarget->failed_reason ?? 'All crawl targets failed';
            $run = $this->crawlRuns->update($run, [
                'status' => CrawlRunStatus::Failed,
                'completed_at' => now(),
                'failed_reason' => $reason,
                'targets_failed' => $this->targets->countFailedForRun($run->id),
            ]);

            $this->observability->runFailed($run, $reason);
            event(new CrawlRunFailed($run, $reason));

            return;
        }

        $run = $this->crawlRuns->update($run, [
            'status' => CrawlRunStatus::Completed,
            'completed_at' => now(),
            'failed_reason' => null,
        ]);

        $this->observability->runCompleted($run);
        event(new CrawlRunCompleted($run));

        if ($run->crawl_type === CrawlType::Contacts->value) {
            event(new ContactsCrawlCompleted(
                connectionKey: $run->connection_key,
                subjectNsid: $run->subject_nsid,
                crawlRunId: $run->id,
                spiderRunId: $run->spider_run_id,
                spiderFrontierItemId: $run->spider_frontier_item_id,
            ));
        }
    }

    public function incrementRunDiscoveryCounter(CrawlRun $run, TaskType $taskType, int $resultCount): void
    {
        $column = match ($taskType) {
            TaskType::ContactsPage, TaskType::SubjectContactsPage => 'contacts_discovered',
            default => 'photos_discovered',
        };

        $this->crawlRuns->incrementDiscoveryCounter($run, $column, $resultCount);
    }

    public function incrementFailedTargets(CrawlRun $run): void
    {
        $this->crawlRuns->incrementFailedTargets($run);
    }

    public function refreshRunCounters(CrawlRun $run): void
    {
        $contactsDiscovered = $this->targets->sumCompletedResultCount($run->id, [
            TaskType::ContactsPage,
            TaskType::SubjectContactsPage,
        ]);

        $photosDiscovered = $this->targets->sumCompletedResultCount($run->id, [
            TaskType::PeoplePhotos,
            TaskType::PhotosetsPhotos,
            TaskType::GalleriesPhotos,
            TaskType::FavoritesPage,
        ]);

        $this->crawlRuns->update($run, [
            'contacts_discovered' => $contactsDiscovered,
            'photos_discovered' => $photosDiscovered,
        ]);
    }

    public function dispatchPendingTargetsForRun(CrawlRun $run): int
    {
        if (XFlickrConfig::globalPause()) {
            return 0;
        }

        $now = CarbonImmutable::now();
        $dispatched = 0;

        $this->targets->chunkPendingForRun($run->id, 50, $now, function (Collection $chunk) use (&$dispatched): void {
            foreach ($chunk as $target) {
                if ($this->dispatchTarget($target)) {
                    $dispatched++;
                }
            }
        });

        return $dispatched;
    }

    public function dispatchTarget(CrawlTarget $target): bool
    {
        if (! $this->targets->tryQueueLock($target)) {
            return false;
        }

        $job = $this->jobFactory->make($target);

        dispatch($job)->onQueue((string) config('xflickr-crawler.queue', 'xflickr'));

        return true;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Spider\Services;

use Illuminate\Support\Facades\DB;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Events\ContactsCrawlCompleted;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Repositories\ConnectionRepository;
use Modules\Crawler\Services\CrawlerRuns;
use Modules\Crawler\Support\XFlickrConfig;
use Modules\Flickr\Exceptions\GlobalCrawlPauseException;
use Modules\Flickr\Services\FlickrAccountsService;
use Modules\Spider\Contracts\ConcurrentRunGuard;
use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Models\SpiderFrontierItem;
use Modules\Spider\Models\SpiderRun;
use Modules\Spider\Repositories\SpiderFrontierRepository;
use Modules\Spider\Repositories\SpiderRunRepository;
use Modules\Spider\Support\SpiderRuntimeConfig;
use RuntimeException;

final class SpiderPlannerService
{
    public function __construct(
        private readonly SpiderRunRepository $runs,
        private readonly SpiderFrontierRepository $frontier,
        private readonly ConcurrentRunGuard $fullPassGuard,
        private readonly FlickrAccountsService $crawls,
        private readonly SpiderRuntimeConfig $spiderConfig,
        private readonly FrontierExpansion $frontierExpansion,
        private readonly ConnectionRepository $connections,
        private readonly CrawlerRuns $crawlerRuns,
    ) {}

    public function isEnabled(): bool
    {
        return $this->spiderConfig->enabled();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createRun(array $attributes): SpiderRun
    {
        /** @var SpiderRun $run */
        $run = $this->runs->create($attributes);

        return $run;
    }

    public function start(Connection $connection): SpiderRun
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Spider mode is disabled. Enable spider.enabled in Settings → General runtime config.');
        }

        if (XFlickrConfig::globalPause()) {
            throw GlobalCrawlPauseException::active();
        }

        if ($this->fullPassGuard->hasActiveRun($connection->connection_key)) {
            throw new RuntimeException('A full contact pass is already active for this account. Stop it before starting spider mode.');
        }

        if ($this->runs->findActiveForConnection($connection->connection_key) !== null) {
            throw new RuntimeException('A spider run is already active for this account.');
        }

        $maxDepth = $this->spiderConfig->maxDepth();

        /** @var SpiderRun $run */
        $run = DB::transaction(function () use ($connection, $maxDepth): SpiderRun {
            return $this->createRun([
                'connection_key' => $connection->connection_key,
                'status' => SpiderRunStatus::Running,
                'max_depth' => $maxDepth,
            ]);
        });

        $this->crawls->crawl(
            $connection,
            CrawlType::Contacts,
            null,
            $run->id,
            null,
        );

        return $run->fresh() ?? $run;
    }

    public function stop(Connection $connection): ?SpiderRun
    {
        $run = $this->runs->findActiveForConnection($connection->connection_key);

        if ($run === null) {
            return null;
        }

        $this->runs->updateRun($run, [
            'status' => SpiderRunStatus::Paused,
            'paused_at' => now(),
        ]);

        return $run->fresh();
    }

    public function expandActiveRuns(): int
    {
        if (! $this->isEnabled() || XFlickrConfig::globalPause()) {
            return 0;
        }

        $expanded = 0;

        foreach ($this->runs->listRunning() as $run) {
            $expanded += $this->expandRun($run);
        }

        return $expanded;
    }

    public function expandRun(SpiderRun $run): int
    {
        if (XFlickrConfig::globalPause()) {
            return 0;
        }

        $connection = $this->connections->findByKey($run->connection_key);

        if (! $connection instanceof Connection) {
            $this->frontierExpansion->pauseMissingConnection($run, $this->runs);

            return 0;
        }

        $limit = $this->spiderConfig->maxNewContactsPerRun();
        $maxTotal = $this->spiderConfig->maxContactsTotal();

        if ($run->contacts_crawled >= $maxTotal) {
            $this->frontierExpansion->completeRun($run, $this->runs);

            return 0;
        }

        $pending = $this->frontier->nextPending($run->id, min($limit, $maxTotal - $run->contacts_crawled));

        if ($pending->isEmpty()) {
            $this->frontierExpansion->maybeCompleteRun($run, $this->frontier, $this->runs);

            return 0;
        }

        $queued = 0;

        foreach ($pending as $item) {
            $this->crawls->crawl($connection, CrawlType::Photos, $item->contact_nsid);
            $this->crawls->crawl(
                $connection,
                CrawlType::Contacts,
                $item->contact_nsid,
                $run->id,
                $item->id,
            );

            $this->frontier->update($item->id, [
                'status' => SpiderFrontierStatus::Queued,
            ]);

            $queued++;
        }

        $this->runs->incrementRun($run, 'contacts_crawled', $queued);

        return $queued;
    }

    public function handleContactsCrawlCompleted(ContactsCrawlCompleted $event): void
    {
        if ($event->spiderRunId === null) {
            return;
        }

        $run = $this->runs->find($event->spiderRunId);

        if (! $run instanceof SpiderRun || $run->status !== SpiderRunStatus::Running) {
            return;
        }

        if ($event->spiderFrontierItemId !== null) {
            $item = $this->frontier->find($event->spiderFrontierItemId);

            if ($item instanceof SpiderFrontierItem && $item->spider_run_id === $run->id) {
                $this->frontier->update($item->id, [
                    'status' => SpiderFrontierStatus::Crawled,
                    'crawled_at' => now(),
                ]);

                $this->crawlerRuns->chunkDiscoveredContacts($event->crawlRunId, 250, function (array $contacts) use ($run, $item): void {
                    $this->frontierExpansion->enqueueDiscovered($run, $this->frontier, $this->runs, $contacts, $item->depth + 1);
                });
            }
        } else {
            $this->crawlerRuns->chunkDiscoveredContacts($event->crawlRunId, 250, function (array $contacts) use ($run): void {
                $this->frontierExpansion->enqueueDiscovered($run, $this->frontier, $this->runs, $contacts, 0);
            });
        }

        $this->frontierExpansion->maybeCompleteRun($run, $this->frontier, $this->runs);
    }

    /**
     * @return array<string, mixed>
     */
    public function statusForConnection(string $connectionKey): array
    {
        $run = $this->runs->findActiveForConnection($connectionKey)
            ?? $this->runs->findLatestForConnection($connectionKey);

        if (! $run instanceof SpiderRun) {
            return [
                'enabled' => $this->isEnabled(),
                'active' => false,
                'run' => null,
            ];
        }

        return [
            'enabled' => $this->isEnabled(),
            'active' => $run->status === SpiderRunStatus::Running,
            'run' => [
                'id' => $run->id,
                'status' => $run->status->value,
                'max_depth' => $run->max_depth,
                'contacts_discovered' => $run->contacts_discovered,
                'contacts_crawled' => $run->contacts_crawled,
                'depth_histogram' => $this->frontier->depthHistogram($run->id),
                'pending' => $this->frontier->countByStatus($run->id, SpiderFrontierStatus::Pending),
                'queued' => $this->frontier->countByStatus($run->id, SpiderFrontierStatus::Queued),
                'crawled' => $this->frontier->countByStatus($run->id, SpiderFrontierStatus::Crawled),
            ],
        ];
    }
}

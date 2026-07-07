<?php

declare(strict_types=1);

namespace App\Services\Flickr;

use App\Enums\SpiderFrontierStatus;
use App\Enums\SpiderRunStatus;
use App\Models\SpiderFrontierItem;
use App\Models\SpiderRun;
use App\Repositories\SpiderFrontierRepository;
use App\Repositories\SpiderRunRepository;
use App\Support\SpiderRuntimeConfig;
use Illuminate\Support\Facades\DB;
use JOOservices\XFlickrCrawler\Enums\CrawlType;
use JOOservices\XFlickrCrawler\Events\ContactsCrawlCompleted;
use JOOservices\XFlickrCrawler\Models\Connection;
use JOOservices\XFlickrCrawler\Support\XFlickrConfig;
use RuntimeException;

final class SpiderPlannerService
{
    public function __construct(
        private readonly SpiderRunRepository $runs,
        private readonly SpiderFrontierRepository $frontier,
        private readonly FlickrCrawlService $crawls,
        private readonly SpiderRuntimeConfig $spiderConfig,
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
        $run = $this->runs->newQuery()->create($attributes);

        return $run;
    }

    public function start(Connection $connection): SpiderRun
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Spider mode is disabled. Enable spider.enabled in Settings → General runtime config.');
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

        $run->update([
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
        $connection = Connection::query()
            ->where('connection_key', $run->connection_key)
            ->first();

        if (! $connection instanceof Connection) {
            $run->update([
                'status' => SpiderRunStatus::Paused,
                'paused_at' => now(),
            ]);

            return 0;
        }

        $limit = $this->spiderConfig->maxNewContactsPerRun();
        $maxTotal = $this->spiderConfig->maxContactsTotal();

        if ($run->contacts_crawled >= $maxTotal) {
            $this->completeRun($run);

            return 0;
        }

        $pending = $this->frontier->nextPending($run->id, min($limit, $maxTotal - $run->contacts_crawled));

        if ($pending->isEmpty()) {
            $this->maybeCompleteRun($run);

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

            $item->update([
                'status' => SpiderFrontierStatus::Queued,
            ]);

            $queued++;
        }

        $run->increment('contacts_crawled', $queued);

        return $queued;
    }

    public function handleContactsCrawlCompleted(ContactsCrawlCompleted $event): void
    {
        if ($event->spiderRunId === null) {
            return;
        }

        $run = $this->runs->newQuery()->find($event->spiderRunId);

        if (! $run instanceof SpiderRun || $run->status !== SpiderRunStatus::Running) {
            return;
        }

        if ($event->spiderFrontierItemId !== null) {
            $item = SpiderFrontierItem::query()->find($event->spiderFrontierItemId);

            if ($item instanceof SpiderFrontierItem && $item->spider_run_id === $run->id) {
                $item->update([
                    'status' => SpiderFrontierStatus::Crawled,
                    'crawled_at' => now(),
                ]);

                $this->enqueueDiscovered($run, $event->discoveredContactNsids, $item->depth + 1);
            }
        } else {
            $this->enqueueDiscovered($run, $event->discoveredContactNsids, 0);
        }

        $this->maybeCompleteRun($run);
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

    /**
     * @param  list<string>  $contactNsids
     */
    private function enqueueDiscovered(SpiderRun $run, array $contactNsids, int $depth): void
    {
        if ($depth > $run->max_depth || $contactNsids === []) {
            return;
        }

        $known = array_flip($this->frontier->knownContactNsids($run->id));
        $discovered = 0;

        foreach ($contactNsids as $contactNsid) {
            if (isset($known[$contactNsid])) {
                continue;
            }

            if ($this->frontier->enqueue($run->id, $contactNsid, $depth)) {
                $discovered++;
            }
        }

        if ($discovered > 0) {
            $run->increment('contacts_discovered', $discovered);
        }
    }

    private function maybeCompleteRun(SpiderRun $run): void
    {
        if ($run->status !== SpiderRunStatus::Running) {
            return;
        }

        if ($this->frontier->countByStatus($run->id, SpiderFrontierStatus::Pending) > 0) {
            return;
        }

        if ($this->frontier->countByStatus($run->id, SpiderFrontierStatus::Queued) > 0) {
            return;
        }

        $this->completeRun($run);
    }

    private function completeRun(SpiderRun $run): void
    {
        $run->update([
            'status' => SpiderRunStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}

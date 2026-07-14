<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use App\Repositories\Crawler\ContactQueryRepository;
use Illuminate\Support\Facades\DB;
use Modules\Contacts\Models\ContactFullPassFrontierItem;
use Modules\Contacts\Models\ContactFullPassRun;
use Modules\Contacts\Repositories\ContactFullPassFrontierRepository;
use Modules\Contacts\Repositories\ContactFullPassRunRepository;
use Modules\Contacts\Support\FullPassRuntimeConfig;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Events\ContactsCrawlCompleted;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Support\XFlickrConfig;
use Modules\Flickr\Exceptions\GlobalCrawlPauseException;
use Modules\Flickr\Services\FlickrCrawlService;
use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Repositories\SpiderRunRepository;
use Modules\Spider\Services\FrontierExpansion;
use RuntimeException;

final class ContactFullPassPlannerService
{
    /** @var list<CrawlType> */
    private const CATALOG_TYPES = [
        CrawlType::Photos,
        CrawlType::Photosets,
        CrawlType::Galleries,
        CrawlType::Favorites,
    ];

    public function __construct(
        private readonly ContactFullPassRunRepository $runs,
        private readonly ContactFullPassFrontierRepository $frontier,
        private readonly SpiderRunRepository $spiderRuns,
        private readonly ContactQueryRepository $contacts,
        private readonly FlickrCrawlService $crawls,
        private readonly FullPassRuntimeConfig $fullPassConfig,
        private readonly FrontierExpansion $frontierExpansion,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createRun(array $attributes): ContactFullPassRun
    {
        /** @var ContactFullPassRun $run */
        $run = $this->runs->newQuery()->create($attributes);

        return $run;
    }

    public function start(Connection $connection): ContactFullPassRun
    {
        if (XFlickrConfig::globalPause()) {
            throw GlobalCrawlPauseException::active();
        }

        if ($this->spiderRuns->findActiveForConnection($connection->connection_key) !== null) {
            throw new RuntimeException('A spider run is already active for this account. Stop it before starting a full contact pass.');
        }

        if ($this->runs->findActiveForConnection($connection->connection_key) !== null) {
            throw new RuntimeException('A full contact pass is already active for this account.');
        }

        $maxDepth = $this->fullPassConfig->maxDepth();

        /** @var ContactFullPassRun $run */
        $run = DB::transaction(function () use ($connection, $maxDepth): ContactFullPassRun {
            return $this->createRun([
                'connection_key' => $connection->connection_key,
                'status' => SpiderRunStatus::Running,
                'max_depth' => $maxDepth,
            ]);
        });

        $this->crawls->crawl($connection, CrawlType::Contacts);

        return $run->fresh() ?? $run;
    }

    public function stop(Connection $connection): ?ContactFullPassRun
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
        if (XFlickrConfig::globalPause()) {
            return 0;
        }

        $expanded = 0;

        foreach ($this->runs->listRunning() as $run) {
            $expanded += $this->expandRun($run);
        }

        return $expanded;
    }

    public function handleContactsCrawlCompleted(ContactsCrawlCompleted $event): void
    {
        if ($event->spiderRunId !== null) {
            return;
        }

        $run = $this->runs->findActiveForConnection($event->connectionKey);

        if (! $run instanceof ContactFullPassRun || $run->status !== SpiderRunStatus::Running) {
            return;
        }

        $subjectNsid = $event->subjectNsid;

        if ($subjectNsid === null || $subjectNsid === '') {
            $this->frontierExpansion->enqueueDiscovered($run, $this->frontier, $event->discoveredContactNsids, 0);
        } else {
            $item = $this->frontier->findByRunAndContactNsid($run->id, $subjectNsid);

            if ($item instanceof ContactFullPassFrontierItem && $item->contact_full_pass_run_id === $run->id) {
                $item->update([
                    'status' => SpiderFrontierStatus::Crawled,
                    'crawled_at' => now(),
                ]);

                $this->frontierExpansion->enqueueDiscovered($run, $this->frontier, $event->discoveredContactNsids, $item->depth + 1);
            }
        }

        $this->expandRun($run);
        $this->frontierExpansion->maybeCompleteRun($run, $this->frontier);
    }

    /**
     * @return array<string, mixed>
     */
    public function previewForConnection(Connection $connection): array
    {
        $activeRun = $this->runs->findActiveForConnection($connection->connection_key);
        $latestRun = $this->runs->findLatestForConnection($connection->connection_key);
        $spiderActive = $this->spiderRuns->findActiveForConnection($connection->connection_key) !== null;

        return [
            'max_depth' => $this->fullPassConfig->maxDepth(),
            'max_contacts_per_batch' => $this->fullPassConfig->maxContactsPerBatch(),
            'max_contacts_total' => $this->fullPassConfig->maxContactsTotal(),
            'saved_contacts_count' => $this->contacts->countForConnection($connection->connection_key),
            'active' => $activeRun !== null,
            'spider_active' => $spiderActive,
            'run' => $this->serializeRun($activeRun ?? $latestRun),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeRun(?ContactFullPassRun $run): ?array
    {
        if (! $run instanceof ContactFullPassRun) {
            return null;
        }

        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'max_depth' => $run->max_depth,
            'contacts_discovered' => $run->contacts_discovered,
            'contacts_crawled' => $run->contacts_crawled,
        ];
    }

    public function expandRun(ContactFullPassRun $run): int
    {
        if ($run->status !== SpiderRunStatus::Running || XFlickrConfig::globalPause()) {
            return 0;
        }

        $connection = Connection::query()
            ->where('connection_key', $run->connection_key)
            ->first();

        if (! $connection instanceof Connection) {
            $this->frontierExpansion->pauseMissingConnection($run);

            return 0;
        }

        $limit = $this->fullPassConfig->maxContactsPerBatch();
        $maxTotal = $this->fullPassConfig->maxContactsTotal();

        if ($run->contacts_crawled >= $maxTotal) {
            $this->frontierExpansion->completeRun($run);

            return 0;
        }

        $pending = $this->frontier->nextPending($run->id, min($limit, $maxTotal - $run->contacts_crawled));

        if ($pending->isEmpty()) {
            $this->frontierExpansion->maybeCompleteRun($run, $this->frontier);

            return 0;
        }

        $queued = 0;

        foreach ($pending as $item) {
            foreach (self::CATALOG_TYPES as $type) {
                $this->crawls->crawl($connection, $type, $item->contact_nsid);
            }

            if ($item->depth < $run->max_depth) {
                $this->crawls->crawl($connection, CrawlType::Contacts, $item->contact_nsid);
                $item->update([
                    'status' => SpiderFrontierStatus::Queued,
                ]);
            } else {
                $item->update([
                    'status' => SpiderFrontierStatus::Crawled,
                    'crawled_at' => now(),
                ]);
            }

            $queued++;
        }

        $run->increment('contacts_crawled', $queued);

        return $queued;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use App\Repositories\Crawler\CrawlRunQueryRepository;
use App\Repositories\Crawler\CrawlTargetQueryRepository;
use Illuminate\Support\Collection;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Support\XFlickrConfig;

final class ContactCrawlStateService
{
    /** @var list<string> */
    private const CONTACT_CRAWL_TYPES = [
        CrawlType::Photos->value,
        CrawlType::Photosets->value,
        CrawlType::Galleries->value,
        CrawlType::Favorites->value,
    ];

    /** @var array<string, list<TaskType>> */
    private const CRAWL_TYPE_TASK_TYPES = [
        CrawlType::Photos->value => [TaskType::PeoplePhotos],
        CrawlType::Favorites->value => [TaskType::FavoritesPage],
        CrawlType::Photosets->value => [TaskType::PhotosetsList, TaskType::PhotosetsPhotos],
        CrawlType::Galleries->value => [TaskType::GalleriesList, TaskType::GalleriesPhotos],
    ];

    public function __construct(
        private readonly CrawlRunQueryRepository $crawlRuns,
        private readonly CrawlTargetQueryRepository $crawlTargets,
    ) {}

    /**
     * @param  list<string>  $contactNsids
     * @param  array<string, array{photos: int, photosets: int, galleries: int, favorites: int}>  $catalogCounts
     * @return array<string, array<string, array{processing: bool, crawled: bool, fetched?: int, total?: int|null}>>
     */
    public function forContacts(Connection $connection, array $contactNsids, array $catalogCounts = []): array
    {
        $states = [];

        foreach ($contactNsids as $contactNsid) {
            $states[$contactNsid] = $this->emptyState();
        }

        if ($contactNsids === []) {
            return $states;
        }

        $runs = $this->crawlRuns->listForContacts(
            $connection->connection_key,
            $contactNsids,
            self::CONTACT_CRAWL_TYPES,
            [CrawlRunStatus::Running->value, CrawlRunStatus::Completed->value],
        );

        $runningRunIds = $runs
            ->where('status', CrawlRunStatus::Running)
            ->pluck('id')
            ->all();

        /** @var Collection<int, Collection<int, CrawlTarget>> $targetsByRun */
        $targetsByRun = $this->crawlTargets->groupedByRunIds($runningRunIds);

        $perPage = XFlickrConfig::crawlInt('per_page', 500);

        foreach ($runs as $run) {
            $contactNsid = (string) $run->subject_nsid;
            $crawlType = (string) $run->crawl_type;

            if (! isset($states[$contactNsid][$crawlType])) {
                continue;
            }

            if ($run->status === CrawlRunStatus::Running) {
                $states[$contactNsid][$crawlType]['processing'] = true;

                $fetched = $catalogCounts[$contactNsid][$crawlType] ?? 0;
                $states[$contactNsid][$crawlType]['fetched'] = $fetched;
                $states[$contactNsid][$crawlType]['total'] = $this->estimateTotal(
                    $targetsByRun->get($run->id, collect()),
                    $crawlType,
                    $contactNsid,
                    $perPage,
                );
            } elseif (! $states[$contactNsid][$crawlType]['processing']) {
                $states[$contactNsid][$crawlType]['crawled'] = true;
                $states[$contactNsid][$crawlType]['fetched'] = $catalogCounts[$contactNsid][$crawlType] ?? 0;
            }
        }

        return $states;
    }

    /**
     * @param  array<string, array{photos: int, photosets: int, galleries: int, favorites: int}>  $catalogCounts
     * @return array<string, array{processing: bool, crawled: bool, fetched?: int, total?: int|null}>
     */
    public function forContact(Connection $connection, string $contactNsid, array $catalogCounts = []): array
    {
        return $this->forContacts($connection, [$contactNsid], $catalogCounts)[$contactNsid] ?? $this->emptyState();
    }

    /**
     * @param  Collection<int, CrawlTarget>  $targets
     */
    private function estimateTotal(Collection $targets, string $crawlType, string $contactNsid, int $perPage): ?int
    {
        $taskTypes = self::CRAWL_TYPE_TASK_TYPES[$crawlType] ?? [];

        if ($taskTypes === []) {
            return null;
        }

        $relevant = $targets->filter(function (CrawlTarget $target) use ($taskTypes, $contactNsid): bool {
            if (! in_array($target->task_type, $taskTypes, true)) {
                return false;
            }

            return (string) $target->subject_nsid === $contactNsid;
        });

        if ($relevant->isEmpty()) {
            return null;
        }

        $estimate = 0;

        foreach ($relevant as $target) {
            if ($target->status === CrawlStatus::Completed) {
                $estimate += max(0, (int) $target->last_result_count);
            } elseif (in_array($target->status, [CrawlStatus::Pending, CrawlStatus::Queued, CrawlStatus::Processing], true)) {
                $estimate += $perPage;
            }
        }

        return $estimate > 0 ? $estimate : null;
    }

    /**
     * @return array<string, array{processing: bool, crawled: bool}>
     */
    private function emptyState(): array
    {
        $state = [];

        foreach (self::CONTACT_CRAWL_TYPES as $crawlType) {
            $state[$crawlType] = [
                'processing' => false,
                'crawled' => false,
            ];
        }

        return $state;
    }
}

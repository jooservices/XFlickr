<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use App\Repositories\Crawler\CatalogQueryRepository;
use App\Repositories\Crawler\CrawlRunQueryRepository;
use App\Repositories\Crawler\CrawlTargetQueryRepository;
use App\Repositories\Crawler\PhotoQueryRepository;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\Connection;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Services\StoredFileService;
use Modules\Transfer\Services\TransferBatchService;

final class ContactStatsService
{
    public function __construct(
        private readonly PhotoQueryRepository $photos,
        private readonly CatalogQueryRepository $catalog,
        private readonly CrawlRunQueryRepository $crawlRuns,
        private readonly CrawlTargetQueryRepository $crawlTargets,
        private readonly StoredFileService $storedFiles,
        private readonly TransferBatchService $batches,
    ) {}

    /**
     * @param  list<string>  $contactNsids
     * @return array<string, array{photos: int, photosets: int, galleries: int, favorites: int}>
     */
    public function catalogCountsFor(Connection $connection, array $contactNsids): array
    {
        $counts = [];

        foreach ($contactNsids as $contactNsid) {
            $counts[$contactNsid] = [
                'photos' => 0,
                'photosets' => 0,
                'galleries' => 0,
                'favorites' => 0,
            ];
        }

        if ($contactNsids === []) {
            return $counts;
        }

        $photos = $this->photos->countsByOwnerNsids($contactNsids);
        $photosets = $this->catalog->photosetCountsByOwnerNsids($contactNsids);
        $galleries = $this->catalog->galleryCountsByOwnerNsids($contactNsids);
        $favorites = $this->catalog->favoriteCountsBySubjectNsids($connection->connection_key, $contactNsids);

        foreach ($contactNsids as $contactNsid) {
            $counts[$contactNsid] = [
                'photos' => $photos[$contactNsid] ?? 0,
                'photosets' => $photosets[$contactNsid] ?? 0,
                'galleries' => $galleries[$contactNsid] ?? 0,
                'favorites' => $favorites[$contactNsid] ?? 0,
            ];
        }

        return $counts;
    }

    /**
     * @param  list<string>  $contactNsids
     * @return array<string, int>
     */
    public function photoCountsFor(array $contactNsids): array
    {
        if ($contactNsids === []) {
            return [];
        }

        $counts = array_fill_keys($contactNsids, 0);

        foreach (array_chunk($contactNsids, 500) as $chunk) {
            foreach ($this->photos->countsByOwnerNsids($chunk) as $nsid => $count) {
                $counts[$nsid] = $count;
            }
        }

        return $counts;
    }

    /**
     * @param  list<string>  $contactNsids
     * @return list<string>
     */
    public function topNsidsByPhotoCount(array $contactNsids, int $limit): array
    {
        return $this->photos->topOwnerNsidsByPhotoCount($contactNsids, $limit);
    }

    /**
     * @param  list<string>  $excludeNsids
     * @return list<string>
     */
    public function topNsidsByPhotoCountForConnection(
        string $connectionKey,
        array $excludeNsids,
        int $limit,
    ): array {
        return $this->photos->topOwnerNsidsByPhotoCountForConnection($connectionKey, $excludeNsids, $limit);
    }

    /**
     * @return array{
     *     photos: array{db: int, with_sizes: int, in_api: int|null},
     *     photosets: array{db: int, in_api: int|null},
     *     favorites: array{db: int, in_api: int|null},
     *     galleries: array{db: int, in_api: int|null},
     * }
     */
    public function detailStatsFor(Connection $connection, string $contactNsid): array
    {
        $counts = $this->catalogCountsFor($connection, [$contactNsid])[$contactNsid] ?? [
            'photos' => 0,
            'photosets' => 0,
            'galleries' => 0,
            'favorites' => 0,
        ];

        $withSizes = $this->photos->countWithSizesForOwner($contactNsid);

        return [
            'photos' => [
                'db' => $counts['photos'],
                'with_sizes' => $withSizes,
                'in_api' => $this->inApiTotal($connection, $contactNsid, CrawlType::Photos->value),
            ],
            'photosets' => [
                'db' => $counts['photosets'],
                'in_api' => $this->inApiTotal($connection, $contactNsid, CrawlType::Photosets->value),
            ],
            'favorites' => [
                'db' => $counts['favorites'],
                'in_api' => $this->inApiTotal($connection, $contactNsid, CrawlType::Favorites->value),
            ],
            'galleries' => [
                'db' => $counts['galleries'],
                'in_api' => $this->inApiTotal($connection, $contactNsid, CrawlType::Galleries->value),
            ],
        ];
    }

    /**
     * @param  list<string>  $contactNsids
     * @return array<string, array{
     *     total: int,
     *     failed: int,
     *     processing: bool,
     *     batch_completed?: int,
     *     batch_total?: int
     * }>
     */
    public function downloadCountsFor(Connection $connection, array $contactNsids): array
    {
        $counts = [];

        foreach ($contactNsids as $contactNsid) {
            $counts[$contactNsid] = [
                'total' => 0,
                'failed' => 0,
                'processing' => false,
            ];
        }

        if ($contactNsids === []) {
            return $counts;
        }

        $storedFileList = $this->storedFiles->originalsForOwners($contactNsids);

        foreach ($storedFileList->groupBy('source_owner') as $ownerNsid => $files) {
            if (! isset($counts[$ownerNsid])) {
                continue;
            }

            $failed = 0;
            $downloaded = 0;

            foreach ($files as $file) {
                if ($file->status === StoredFileStatus::Failed->value) {
                    $failed++;

                    continue;
                }

                if ($file->status !== StoredFileStatus::Completed->value) {
                    continue;
                }

                $downloaded++;
            }

            $counts[$ownerNsid]['total'] = $downloaded;
            $counts[$ownerNsid]['failed'] = $failed;
        }

        $runningBatches = $this->batches->runningDownloadsForSubjects($connection->connection_key, $contactNsids);

        foreach ($runningBatches->groupBy('subject_nsid') as $subjectNsid => $batchGroup) {
            if (! isset($counts[$subjectNsid])) {
                continue;
            }

            $counts[$subjectNsid]['processing'] = true;
            $counts[$subjectNsid]['batch_completed'] = (int) $batchGroup->sum('completed_count');
            $counts[$subjectNsid]['batch_total'] = (int) $batchGroup->sum('total_count');
        }

        return $counts;
    }

    private function inApiTotal(Connection $connection, string $contactNsid, string $crawlType): ?int
    {
        $run = $this->crawlRuns->findLatestCompleted(
            $connection->connection_key,
            $contactNsid,
            $crawlType,
        );

        if ($run === null) {
            return null;
        }

        if ($crawlType === CrawlType::Photos->value) {
            return (int) $run->photos_discovered;
        }

        $taskTypes = match ($crawlType) {
            CrawlType::Photosets->value => [TaskType::PhotosetsList, TaskType::PhotosetsPhotos],
            CrawlType::Galleries->value => [TaskType::GalleriesList, TaskType::GalleriesPhotos],
            CrawlType::Favorites->value => [TaskType::FavoritesPage],
            default => [],
        };

        if ($taskTypes === []) {
            return null;
        }

        return $this->crawlTargets->sumCompletedResults($run->id, $taskTypes, $contactNsid);
    }
}

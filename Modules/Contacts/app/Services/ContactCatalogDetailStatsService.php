<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use App\Repositories\Crawler\CrawlRunQueryRepository;
use App\Repositories\Crawler\CrawlTargetQueryRepository;
use App\Repositories\Crawler\PhotoQueryRepository;
use JOOservices\XFlickrCrawler\Enums\CrawlType;
use JOOservices\XFlickrCrawler\Enums\TaskType;
use JOOservices\XFlickrCrawler\Models\Connection;

final class ContactCatalogDetailStatsService
{
    public function __construct(
        private readonly ContactCatalogCountsService $catalogCounts,
        private readonly PhotoQueryRepository $photos,
        private readonly CrawlRunQueryRepository $crawlRuns,
        private readonly CrawlTargetQueryRepository $crawlTargets,
    ) {}

    /**
     * @return array{
     *     photos: array{db: int, with_sizes: int, in_api: int|null},
     *     photosets: array{db: int, in_api: int|null},
     *     favorites: array{db: int, in_api: int|null},
     *     galleries: array{db: int, in_api: int|null},
     * }
     */
    public function forContact(Connection $connection, string $contactNsid): array
    {
        $counts = $this->catalogCounts->forContacts($connection, [$contactNsid])[$contactNsid] ?? [
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

        $total = $this->crawlTargets->sumCompletedResults($run->id, $taskTypes, $contactNsid);

        return $total;
    }
}

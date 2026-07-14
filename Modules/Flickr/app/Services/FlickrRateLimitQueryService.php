<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use App\Repositories\Crawler\CatalogQueryRepository;
use App\Repositories\Crawler\ContactQueryRepository;
use App\Repositories\Crawler\PhotoQueryRepository;
use Modules\Crawler\Facades\FlickrService;
use Modules\Flickr\Support\ConnectionPresenter;

final class FlickrRateLimitQueryService
{
    public function __construct(
        private readonly FlickrRateLimitPresenter $rateLimit,
        private readonly ContactQueryRepository $contacts,
        private readonly PhotoQueryRepository $photos,
        private readonly CatalogQueryRepository $catalog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $connections = FlickrService::connections()->list();
        $active = FlickrService::connections()->active();
        $connectionKeys = $connections->map(fn ($connection) => $connection->connection_key)->values()->all();

        $contactsByConnection = $this->contacts->countsGroupedByConnection($connectionKeys);
        $photosByConnection = $this->photos->countsGroupedByConnection($connectionKeys);
        $photosetsByConnection = $this->catalog->photosetCountsGroupedByConnection($connectionKeys);
        $galleriesByConnection = $this->catalog->galleryCountsGroupedByConnection($connectionKeys);
        $favoritesByConnection = $this->catalog->favoriteCountsGroupedByConnection($connectionKeys);

        $accounts = [];
        foreach ($connections as $connection) {
            $key = (string) $connection->connection_key;

            $accounts[] = [
                'account' => ConnectionPresenter::toArray($connection),
                'rate_limit' => $this->rateLimit->present($key),
                'catalog_counts' => [
                    'contacts_db' => $contactsByConnection[$key] ?? 0,
                    'photos_db' => $photosByConnection[$key] ?? 0,
                    'photosets_db' => $photosetsByConnection[$key] ?? 0,
                    'galleries_db' => $galleriesByConnection[$key] ?? 0,
                    'favorites_db' => $favoritesByConnection[$key] ?? 0,
                ],
            ];
        }

        return [
            'generated_at' => now()->toISOString(),
            'active_connection_key' => $active?->connection_key,
            'accounts' => $accounts,
        ];
    }
}

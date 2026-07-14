<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use Modules\Crawler\Repositories\ConnectionContactRepository;
use Modules\Crawler\Repositories\FavoriteRepository;
use Modules\Crawler\Repositories\PhotoRepository;

final class FlickrFavoritesPersistence
{
    public function __construct(
        private readonly FlickrCatalogService $catalog,
        private readonly PhotoRepository $photos,
        private readonly FavoriteRepository $favorites,
        private readonly ConnectionContactRepository $connectionContacts,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $photoItems
     */
    public function persistPage(array $photoItems, string $connectionKey, string $subjectNsid): int
    {
        if ($photoItems === []) {
            return 0;
        }

        $this->catalog->persistPhotoPage($photoItems);

        $flickrPhotoIds = [];
        $ownerByPhotoId = [];
        foreach ($photoItems as $photoData) {
            $photoId = (string) ($photoData['id'] ?? '');
            if ($photoId === '') {
                continue;
            }

            $flickrPhotoIds[] = $photoId;
            $ownerByPhotoId[$photoId] = (string) ($photoData['owner'] ?? '');
        }

        $internalIds = $this->photos->idsByFlickrPhotoIds($flickrPhotoIds);
        $now = now()->toDateTimeString();
        $rows = [];

        foreach ($internalIds as $flickrPhotoId => $internalId) {
            $rows[] = [
                'connection_key' => $connectionKey,
                'subject_nsid' => $subjectNsid,
                'xflickr_photo_id' => $internalId,
                'photo_owner_nsid' => $ownerByPhotoId[$flickrPhotoId] !== ''
                    ? $ownerByPhotoId[$flickrPhotoId]
                    : null,
                'discovered_at' => $now,
            ];
        }

        $count = $this->favorites->upsertMany($rows);

        $ownerNsids = array_values(array_unique(array_filter(
            array_column($rows, 'photo_owner_nsid'),
            static fn (?string $nsid): bool => is_string($nsid) && $nsid !== '',
        )));

        if ($ownerNsids !== []) {
            $this->connectionContacts->upsertMany($connectionKey, $ownerNsids);
        }

        return $count;
    }
}

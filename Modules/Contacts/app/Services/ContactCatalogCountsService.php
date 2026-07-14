<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use App\Repositories\Crawler\CatalogQueryRepository;
use App\Repositories\Crawler\PhotoQueryRepository;
use Modules\Crawler\Models\Connection;

final class ContactCatalogCountsService
{
    public function __construct(
        private readonly PhotoQueryRepository $photos,
        private readonly CatalogQueryRepository $catalog,
    ) {}

    /**
     * @param  list<string>  $contactNsids
     * @return array<string, array{photos: int, photosets: int, galleries: int, favorites: int}>
     */
    public function forContacts(Connection $connection, array $contactNsids): array
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
}

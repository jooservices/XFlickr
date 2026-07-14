<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Crawler\DTO\PhotoCountsDto;
use Modules\Crawler\Models\ConnectionContact;
use Modules\Crawler\Models\Contact;
use Modules\Crawler\Repositories\ConnectionContactRepository;
use Modules\Crawler\Repositories\ContactRepository;
use Modules\Crawler\Repositories\FavoriteRepository;
use Modules\Crawler\Repositories\GalleryRepository;
use Modules\Crawler\Repositories\PhotoRepository;
use Modules\Crawler\Repositories\PhotosetRepository;

final class CrawlerCatalog
{
    public function __construct(
        private readonly PhotoRepository $photos,
        private readonly PhotosetRepository $photosets,
        private readonly GalleryRepository $galleries,
        private readonly FavoriteRepository $favorites,
        private readonly ConnectionContactRepository $connectionContacts,
        private readonly ContactRepository $contacts,
    ) {}

    public function countsForSubject(string $connectionKey, string $subjectNsid): PhotoCountsDto
    {
        return new PhotoCountsDto(
            photos: $this->photos->countByOwnerNsid($subjectNsid),
            photosets: $this->photosets->countByOwnerNsid($subjectNsid),
            galleries: $this->galleries->countByOwnerNsid($subjectNsid),
            favorites: $this->favorites->countForSubject($connectionKey, $subjectNsid),
        );
    }

    /**
     * @return Collection<int, ConnectionContact>
     */
    public function contactsForConnection(string $connectionKey): Collection
    {
        return $this->connectionContacts->listByConnectionKey($connectionKey);
    }

    /**
     * @return Collection<int, Contact>
     */
    public function contactProfilesForConnection(string $connectionKey): Collection
    {
        $nsids = $this->contactsForConnection($connectionKey)
            ->pluck('contact_nsid')
            ->all();

        return $this->contacts->findByNsidsOrderedByUsername($nsids);
    }

    /**
     * @return LengthAwarePaginator<int, Contact>
     */
    public function contactProfilesForConnectionPaginated(
        string $connectionKey,
        ?string $search,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        return $this->contacts->paginateProfilesForConnection($connectionKey, $search, $page, $perPage);
    }
}

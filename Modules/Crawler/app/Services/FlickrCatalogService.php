<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use Illuminate\Support\Facades\DB;
use Modules\Crawler\Repositories\ConnectionContactRepository;
use Modules\Crawler\Repositories\ContactRepository;
use Modules\Crawler\Repositories\GalleryRepository;
use Modules\Crawler\Repositories\PhotoRepository;
use Modules\Crawler\Repositories\PhotosetRepository;
use Modules\Crawler\Repositories\PivotRepository;
use Modules\Crawler\Repositories\SubjectContactRepository;

final class FlickrCatalogService
{
    public function __construct(
        private readonly ContactRepository $contacts,
        private readonly ConnectionContactRepository $connectionContacts,
        private readonly PhotoRepository $photos,
        private readonly PhotosetRepository $photosets,
        private readonly GalleryRepository $galleries,
        private readonly PivotRepository $pivots,
        private readonly SubjectContactRepository $subjectContacts,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $contactItems
     */
    public function persistContacts(array $contactItems, ?string $connectionKey = null): int
    {
        $rows = [];
        foreach ($contactItems as $contact) {
            $row = $this->mapContactRow($contact);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        if ($rows === []) {
            return 0;
        }

        $count = $this->contacts->upsertMany($rows);

        if ($connectionKey !== null && $connectionKey !== '') {
            $this->connectionContacts->upsertMany(
                $connectionKey,
                array_column($rows, 'nsid'),
            );
        }

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $contactItems
     */
    public function persistSubjectContacts(
        array $contactItems,
        string $connectionKey,
        string $subjectNsid,
        ?int $crawlRunId = null,
    ): int {
        $rows = [];
        foreach ($contactItems as $contact) {
            $row = $this->mapContactRow($contact);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        if ($rows === []) {
            return 0;
        }

        $count = $this->contacts->upsertMany($rows);

        if ($connectionKey !== '') {
            $this->connectionContacts->upsertMany(
                $connectionKey,
                array_column($rows, 'nsid'),
            );
            $this->subjectContacts->upsertMany(
                $connectionKey,
                $subjectNsid,
                array_column($rows, 'nsid'),
                $crawlRunId,
            );
        }

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $photoItems
     */
    public function persistPhotoPage(array $photoItems, ?string $defaultOwnerNsid = null): int
    {
        $photoRows = [];
        $ownerRows = [];

        foreach ($photoItems as $photoData) {
            $photoRow = $this->mapPhotoRow($photoData, $defaultOwnerNsid);
            if ($photoRow === null) {
                continue;
            }

            $photoRows[] = $photoRow;
            $ownerRow = $this->mapOwnerFromPhoto($photoData, $photoRow['owner_nsid']);
            if ($ownerRow !== null) {
                $ownerRows[$ownerRow['nsid']] = $ownerRow;
            }
        }

        return DB::transaction(function () use ($photoRows, $ownerRows): int {
            if ($ownerRows !== []) {
                $this->contacts->upsertMany(array_values($ownerRows));
            }

            return $this->photos->upsertMany($photoRows);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $photosetItems
     */
    public function persistPhotosets(array $photosetItems, string $ownerNsid): int
    {
        $rows = [];
        foreach ($photosetItems as $photosetData) {
            $row = $this->mapPhotosetRow($photosetData, $ownerNsid);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $this->photosets->upsertMany($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $galleryItems
     */
    public function persistGalleries(array $galleryItems, string $ownerNsid): int
    {
        $rows = [];
        foreach ($galleryItems as $galleryData) {
            $row = $this->mapGalleryRow($galleryData, $ownerNsid);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $this->galleries->upsertMany($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $photoItems
     */
    public function persistPhotosetPhotoPage(
        array $photoItems,
        string $ownerNsid,
        string $flickrPhotosetId,
    ): int {
        $count = $this->persistPhotoPage($photoItems, $ownerNsid);
        $this->attachPhotosToPhotoset($photoItems, $flickrPhotosetId);

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $photoItems
     */
    public function persistGalleryPhotoPage(
        array $photoItems,
        string $ownerNsid,
        string $flickrGalleryId,
    ): int {
        $count = $this->persistPhotoPage($photoItems, $ownerNsid);
        $this->attachPhotosToGallery($photoItems, $flickrGalleryId);

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $photoItems
     */
    private function attachPhotosToPhotoset(array $photoItems, string $flickrPhotosetId): void
    {
        $photosetIds = $this->photosets->idsByFlickrPhotosetIds([$flickrPhotosetId]);
        $photosetId = $photosetIds[$flickrPhotosetId] ?? null;
        if ($photosetId === null) {
            return;
        }

        $flickrPhotoIds = [];
        foreach ($photoItems as $photoData) {
            $id = (string) ($photoData['id'] ?? '');
            if ($id !== '') {
                $flickrPhotoIds[] = $id;
            }
        }

        $photoIds = $this->photos->idsByFlickrPhotoIds($flickrPhotoIds);
        $now = now()->toDateTimeString();
        $rows = [];
        foreach ($photoIds as $internalId) {
            $rows[] = [
                'xflickr_photoset_id' => $photosetId,
                'xflickr_photo_id' => $internalId,
                'discovered_at' => $now,
            ];
        }

        $this->pivots->attachPhotosetPhotos($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $photoItems
     */
    private function attachPhotosToGallery(array $photoItems, string $flickrGalleryId): void
    {
        $galleryIds = $this->galleries->idsByFlickrGalleryIds([$flickrGalleryId]);
        $galleryId = $galleryIds[$flickrGalleryId] ?? null;
        if ($galleryId === null) {
            return;
        }

        $flickrPhotoIds = [];
        foreach ($photoItems as $photoData) {
            $id = (string) ($photoData['id'] ?? '');
            if ($id !== '') {
                $flickrPhotoIds[] = $id;
            }
        }

        $photoIds = $this->photos->idsByFlickrPhotoIds($flickrPhotoIds);
        $now = now()->toDateTimeString();
        $rows = [];
        foreach ($photoIds as $internalId) {
            $rows[] = [
                'xflickr_gallery_id' => $galleryId,
                'xflickr_photo_id' => $internalId,
                'discovered_at' => $now,
            ];
        }

        $this->pivots->attachGalleryPhotos($rows);
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>|null
     */
    private function mapContactRow(array $contact): ?array
    {
        $nsid = (string) ($contact['nsid'] ?? '');
        if ($nsid === '') {
            return null;
        }

        return [
            'nsid' => $nsid,
            'username' => $this->stringValue($contact['username'] ?? null),
            'realname' => $this->stringValue($contact['realname'] ?? null),
            'friend' => (bool) ((int) ($contact['friend'] ?? 0)),
            'family' => (bool) ((int) ($contact['family'] ?? 0)),
            'raw_payload' => json_encode($contact, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param  array<string, mixed>  $photoData
     * @return array<string, mixed>|null
     */
    private function mapPhotoRow(array $photoData, ?string $defaultOwnerNsid): ?array
    {
        $photoId = (string) ($photoData['id'] ?? '');
        if ($photoId === '') {
            return null;
        }

        $ownerNsid = (string) ($photoData['owner'] ?? $defaultOwnerNsid ?? 'unknown');

        return [
            'flickr_photo_id' => $photoId,
            'owner_nsid' => $ownerNsid !== '' ? $ownerNsid : 'unknown',
            'title' => $this->stringValue($photoData['title'] ?? null),
            'secret' => isset($photoData['secret']) ? (string) $photoData['secret'] : null,
            'server' => isset($photoData['server']) ? (string) $photoData['server'] : null,
            'farm' => isset($photoData['farm']) ? (int) $photoData['farm'] : null,
            'raw_payload' => json_encode($photoData, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param  array<string, mixed>  $photoData
     * @return array<string, mixed>|null
     */
    private function mapOwnerFromPhoto(array $photoData, string $ownerNsid): ?array
    {
        if ($ownerNsid === '' || $ownerNsid === 'unknown') {
            return null;
        }

        return [
            'nsid' => $ownerNsid,
            'username' => $this->stringValue($photoData['ownername'] ?? $photoData['username'] ?? null),
            'realname' => $this->stringValue($photoData['realname'] ?? null),
            'friend' => false,
            'family' => false,
            'raw_payload' => json_encode(['source' => 'photo_owner', 'nsid' => $ownerNsid], JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param  array<string, mixed>  $photosetData
     * @return array<string, mixed>|null
     */
    private function mapPhotosetRow(array $photosetData, string $ownerNsid): ?array
    {
        $photosetId = (string) ($photosetData['id'] ?? '');
        if ($photosetId === '') {
            return null;
        }

        return [
            'flickr_photoset_id' => $photosetId,
            'owner_nsid' => $ownerNsid,
            'title' => $this->stringValue($photosetData['title'] ?? null),
            'description' => $this->stringValue($photosetData['description'] ?? null),
            'photo_count' => isset($photosetData['photos']) ? (int) $photosetData['photos'] : 0,
            'raw_payload' => json_encode($photosetData, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param  array<string, mixed>  $galleryData
     * @return array<string, mixed>|null
     */
    private function mapGalleryRow(array $galleryData, string $ownerNsid): ?array
    {
        $galleryId = (string) ($galleryData['id'] ?? '');
        if ($galleryId === '') {
            return null;
        }

        return [
            'flickr_gallery_id' => $galleryId,
            'owner_nsid' => $ownerNsid,
            'title' => $this->stringValue($galleryData['title'] ?? null),
            'description' => $this->stringValue($galleryData['description'] ?? null),
            'photo_count' => isset($galleryData['count']) ? (int) $galleryData['count'] : 0,
            'raw_payload' => json_encode($galleryData, JSON_THROW_ON_ERROR),
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            if (array_key_exists('_content', $value)) {
                $value = $value['_content'];
            } else {
                return null;
            }
        }

        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Crawler\Models\ConnectionContact;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Support\XFlickrConfig;

final class PhotoQueryRepository
{
    /**
     * @param  list<string>  $columns
     * @return Collection<int, Photo>
     */
    public function listByOwnerNsid(string $ownerNsid, array $columns = ['id', 'flickr_photo_id', 'owner_nsid']): Collection
    {
        return Photo::query()
            ->where('owner_nsid', $ownerNsid)
            ->get($columns);
    }

    public function existsForOwnerNsid(string $ownerNsid): bool
    {
        return Photo::query()
            ->where('owner_nsid', $ownerNsid)
            ->exists();
    }

    /**
     * @param  list<string>  $columns
     * @param  callable(Collection<int, Photo>): void  $callback
     */
    public function chunkByOwnerNsid(
        string $ownerNsid,
        int $chunkSize,
        callable $callback,
        array $columns = ['id', 'flickr_photo_id', 'owner_nsid'],
    ): void {
        Photo::query()
            ->where('owner_nsid', $ownerNsid)
            ->select($columns)
            ->orderBy('id')
            ->chunk($chunkSize, $callback);
    }

    /**
     * @param  list<string>  $columns
     */
    public function findByFlickrPhotoId(string $flickrPhotoId, array $columns = ['*']): ?Photo
    {
        return Photo::query()
            ->where('flickr_photo_id', $flickrPhotoId)
            ->first($columns);
    }

    /**
     * @param  list<string>  $flickrPhotoIds
     * @param  list<string>  $columns
     * @return Collection<int, Photo>
     */
    public function listByFlickrPhotoIds(
        array $flickrPhotoIds,
        array $columns = ['id', 'flickr_photo_id', 'owner_nsid'],
    ): Collection {
        if ($flickrPhotoIds === []) {
            return collect();
        }

        return Photo::query()
            ->whereIn('flickr_photo_id', $flickrPhotoIds)
            ->get($columns);
    }

    public function countAll(): int
    {
        return Photo::query()->count();
    }

    public function countWithSizes(): int
    {
        return Photo::query()->whereNotNull('raw_payload->sizes')->count();
    }

    public function countForConnection(string $connectionKey): int
    {
        return Photo::query()
            ->whereIn(
                'owner_nsid',
                ConnectionContact::query()
                    ->where('connection_key', $connectionKey)
                    ->select('contact_nsid'),
            )
            ->count();
    }

    public function countWithSizesForConnection(string $connectionKey): int
    {
        return Photo::query()
            ->whereIn(
                'owner_nsid',
                ConnectionContact::query()
                    ->where('connection_key', $connectionKey)
                    ->select('contact_nsid'),
            )
            ->whereNotNull('raw_payload->sizes')
            ->count();
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array<string, int>
     */
    public function countsGroupedByConnection(array $connectionKeys): array
    {
        return $this->aggregatePhotosGroupedByConnection($connectionKeys, withSizes: false);
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array<string, int>
     */
    public function countsWithSizesGroupedByConnection(array $connectionKeys): array
    {
        return $this->aggregatePhotosGroupedByConnection($connectionKeys, withSizes: true);
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array<string, int>
     */
    private function aggregatePhotosGroupedByConnection(array $connectionKeys, bool $withSizes): array
    {
        if ($connectionKeys === []) {
            return [];
        }

        $contactsTable = (new ConnectionContact)->getTable();
        $photosTable = (new Photo)->getTable();

        $query = DB::table($photosTable)
            ->join($contactsTable, "{$photosTable}.owner_nsid", '=', "{$contactsTable}.contact_nsid")
            ->whereIn("{$contactsTable}.connection_key", $connectionKeys)
            ->selectRaw("{$contactsTable}.connection_key, count(*) as aggregate")
            ->groupBy("{$contactsTable}.connection_key");

        if ($withSizes) {
            $query->whereNotNull("{$photosTable}.raw_payload->sizes");
        }

        return $query
            ->pluck('aggregate', 'connection_key')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    public function countWithSizesForOwner(string $ownerNsid): int
    {
        return Photo::query()
            ->where('owner_nsid', $ownerNsid)
            ->whereNotNull('raw_payload->sizes')
            ->count();
    }

    /**
     * @param  list<string>  $ownerNsids
     * @return array<string, int>
     */
    public function countsByOwnerNsids(array $ownerNsids): array
    {
        if ($ownerNsids === []) {
            return [];
        }

        return Photo::query()
            ->whereIn('owner_nsid', $ownerNsids)
            ->groupBy('owner_nsid')
            ->selectRaw('owner_nsid, count(*) as aggregate')
            ->pluck('aggregate', 'owner_nsid')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  list<int>  $internalPhotoIds
     * @return array{photoset_rows: Collection<int, object>, gallery_rows: Collection<int, object>}
     */
    public function photosetAndGalleryMemberships(array $internalPhotoIds): array
    {
        if ($internalPhotoIds === []) {
            return [
                'photoset_rows' => collect(),
                'gallery_rows' => collect(),
            ];
        }

        $photosetRows = DB::table(XFlickrConfig::table('photoset_photo'))
            ->join(
                XFlickrConfig::table('photosets'),
                XFlickrConfig::table('photoset_photo').'.xflickr_photoset_id',
                '=',
                XFlickrConfig::table('photosets').'.id',
            )
            ->whereIn(XFlickrConfig::table('photoset_photo').'.xflickr_photo_id', $internalPhotoIds)
            ->orderBy(XFlickrConfig::table('photosets').'.title')
            ->get([
                XFlickrConfig::table('photoset_photo').'.xflickr_photo_id as photo_id',
                XFlickrConfig::table('photosets').'.flickr_photoset_id',
                XFlickrConfig::table('photosets').'.title',
            ]);

        $galleryRows = DB::table(XFlickrConfig::table('gallery_photo'))
            ->join(
                XFlickrConfig::table('galleries'),
                XFlickrConfig::table('gallery_photo').'.xflickr_gallery_id',
                '=',
                XFlickrConfig::table('galleries').'.id',
            )
            ->whereIn(XFlickrConfig::table('gallery_photo').'.xflickr_photo_id', $internalPhotoIds)
            ->orderBy(XFlickrConfig::table('galleries').'.title')
            ->get([
                XFlickrConfig::table('gallery_photo').'.xflickr_photo_id as photo_id',
                XFlickrConfig::table('galleries').'.flickr_gallery_id',
                XFlickrConfig::table('galleries').'.title',
            ]);

        return [
            'photoset_rows' => $photosetRows,
            'gallery_rows' => $galleryRows,
        ];
    }

    /**
     * @return Builder<Photo>
     */
    public function ownerCountSubquery(): Builder
    {
        return Photo::query()
            ->selectRaw('owner_nsid as contact_nsid, count(*) as aggregate')
            ->groupBy('owner_nsid');
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function updateRawPayload(Photo $photo, array $rawPayload): void
    {
        $photo->update(['raw_payload' => $rawPayload]);
    }
}

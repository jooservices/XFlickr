<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use App\Support\Query\QuerySorter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use JOOservices\XFlickrCrawler\Models\ConnectionContact;
use JOOservices\XFlickrCrawler\Models\Favorite;
use JOOservices\XFlickrCrawler\Models\Gallery;
use JOOservices\XFlickrCrawler\Models\Photo;
use JOOservices\XFlickrCrawler\Models\Photoset;

final class CatalogQueryRepository
{
    /** @var list<string> */
    public const PHOTO_SORTS = ['title', 'flickr_photo_id', 'owner_nsid', 'id'];

    /** @var list<string> */
    public const PHOTOSET_SORTS = ['title', 'photo_count', 'owner_nsid', 'flickr_photoset_id', 'id'];

    /** @var list<string> */
    public const GALLERY_SORTS = ['title', 'photo_count', 'owner_nsid', 'flickr_gallery_id', 'id'];

    /** @var list<string> */
    public const FAVORITE_SORTS = ['subject_nsid', 'photo_owner_nsid', 'xflickr_photo_id', 'discovered_at', 'id'];

    public function __construct(
        private readonly QuerySorter $sorter,
    ) {}

    public function paginatePhotos(?string $ownerNsid, string $sort, string $direction, int $perPage, int $page): LengthAwarePaginator
    {
        $query = Photo::query();
        if ($ownerNsid !== null && $ownerNsid !== '') {
            $query->where('owner_nsid', $ownerNsid);
        }

        $query = $this->sorter->apply($query, $sort, $direction, self::PHOTO_SORTS);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function paginatePhotosets(?string $ownerNsid, string $sort, string $direction, int $perPage, int $page): LengthAwarePaginator
    {
        $query = Photoset::query();
        if ($ownerNsid !== null && $ownerNsid !== '') {
            $query->where('owner_nsid', $ownerNsid);
        }

        $query = $this->sorter->apply($query, $sort, $direction, self::PHOTOSET_SORTS);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function paginateGalleries(?string $ownerNsid, string $sort, string $direction, int $perPage, int $page): LengthAwarePaginator
    {
        $query = Gallery::query();
        if ($ownerNsid !== null && $ownerNsid !== '') {
            $query->where('owner_nsid', $ownerNsid);
        }

        $query = $this->sorter->apply($query, $sort, $direction, self::GALLERY_SORTS);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function paginateFavorites(
        ?string $connectionKey,
        ?string $subjectNsid,
        string $sort,
        string $direction,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        $query = Favorite::query()->with('photo');

        if ($connectionKey !== null && $connectionKey !== '') {
            $query->where('connection_key', $connectionKey);
        }

        if ($subjectNsid !== null && $subjectNsid !== '') {
            $query->where('subject_nsid', $subjectNsid);
        }

        $query = $this->sorter->apply($query, $sort, $direction, self::FAVORITE_SORTS);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  list<string>  $ownerNsids
     * @return array<string, int>
     */
    public function countPhotosetsForConnection(string $connectionKey): int
    {
        return Photoset::query()
            ->whereIn(
                'owner_nsid',
                ConnectionContact::query()
                    ->where('connection_key', $connectionKey)
                    ->select('contact_nsid'),
            )
            ->count();
    }

    public function countGalleriesForConnection(string $connectionKey): int
    {
        return Gallery::query()
            ->whereIn(
                'owner_nsid',
                ConnectionContact::query()
                    ->where('connection_key', $connectionKey)
                    ->select('contact_nsid'),
            )
            ->count();
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array<string, int>
     */
    public function photosetCountsGroupedByConnection(array $connectionKeys): array
    {
        return $this->aggregateCatalogGroupedByConnection(Photoset::query(), $connectionKeys);
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array<string, int>
     */
    public function galleryCountsGroupedByConnection(array $connectionKeys): array
    {
        return $this->aggregateCatalogGroupedByConnection(Gallery::query(), $connectionKeys);
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  list<string>  $connectionKeys
     * @return array<string, int>
     */
    private function aggregateCatalogGroupedByConnection(Builder $query, array $connectionKeys): array
    {
        if ($connectionKeys === []) {
            return [];
        }

        $model = $query->getModel();
        $catalogTable = $model->getTable();
        $contactsTable = (new ConnectionContact)->getTable();

        return DB::table($catalogTable)
            ->join($contactsTable, "{$catalogTable}.owner_nsid", '=', "{$contactsTable}.contact_nsid")
            ->whereIn("{$contactsTable}.connection_key", $connectionKeys)
            ->selectRaw("{$contactsTable}.connection_key, count(*) as aggregate")
            ->groupBy("{$contactsTable}.connection_key")
            ->pluck('aggregate', 'connection_key')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  list<string>  $ownerNsids
     * @return array<string, int>
     */
    public function photosetCountsByOwnerNsids(array $ownerNsids): array
    {
        if ($ownerNsids === []) {
            return [];
        }

        return Photoset::query()
            ->whereIn('owner_nsid', $ownerNsids)
            ->groupBy('owner_nsid')
            ->selectRaw('owner_nsid, count(*) as aggregate')
            ->pluck('aggregate', 'owner_nsid')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  list<string>  $ownerNsids
     * @return array<string, int>
     */
    public function galleryCountsByOwnerNsids(array $ownerNsids): array
    {
        if ($ownerNsids === []) {
            return [];
        }

        return Gallery::query()
            ->whereIn('owner_nsid', $ownerNsids)
            ->groupBy('owner_nsid')
            ->selectRaw('owner_nsid, count(*) as aggregate')
            ->pluck('aggregate', 'owner_nsid')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  list<string>  $subjectNsids
     * @return array<string, int>
     */
    public function favoriteCountsBySubjectNsids(string $connectionKey, array $subjectNsids): array
    {
        if ($subjectNsids === []) {
            return [];
        }

        return Favorite::query()
            ->where('connection_key', $connectionKey)
            ->whereIn('subject_nsid', $subjectNsids)
            ->groupBy('subject_nsid')
            ->selectRaw('subject_nsid, count(*) as aggregate')
            ->pluck('aggregate', 'subject_nsid')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    public function photosetCountSubquery(): Builder
    {
        return Photoset::query()
            ->selectRaw('owner_nsid as contact_nsid, count(*) as aggregate')
            ->groupBy('owner_nsid');
    }

    public function galleryCountSubquery(): Builder
    {
        return Gallery::query()
            ->selectRaw('owner_nsid as contact_nsid, count(*) as aggregate')
            ->groupBy('owner_nsid');
    }

    public function favoriteCountSubquery(string $connectionKey): Builder
    {
        return Favorite::query()
            ->where('connection_key', $connectionKey)
            ->selectRaw('subject_nsid as contact_nsid, count(*) as aggregate')
            ->groupBy('subject_nsid');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Repositories\Crawler\CatalogQueryRepository;
use App\Support\Query\QuerySorter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Catalog\Support\CollectionCatalogPresenter;
use Modules\Catalog\Support\PhotoCatalogPresenter;

final class CatalogQueryService
{
    public function __construct(
        private readonly CatalogQueryRepository $catalog,
        private readonly QuerySorter $sorter,
        private readonly PhotoCatalogPresenter $photoPresenter,
        private readonly CollectionCatalogPresenter $collectionPresenter,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function photoset(int $photosetId): ?array
    {
        $photoset = $this->catalog->findPhotoset($photosetId);

        if ($photoset === null) {
            return null;
        }

        return $this->collectionPresenter->presentPhotoset($photoset);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function photos(
        ?string $ownerNsid,
        ?int $photosetId,
        string $sort,
        string $direction,
        int $perPage,
        int $page,
    ): array {
        $paginator = $this->catalog->paginatePhotos(
            $ownerNsid,
            $photosetId,
            $sort,
            $direction,
            $perPage,
            $page,
        );

        return [
            'data' => $this->photoPresenter->presentPage($paginator->items()),
            'meta' => $this->meta($paginator, $sort, $direction, CatalogQueryRepository::PHOTO_SORTS),
        ];
    }

    /**
     * @param  list<string>  $flickrPhotoIds
     * @return list<array{
     *     flickr_photo_id: string,
     *     download_status: string,
     *     stored_file_uuid: string|null,
     *     stored_file_view_url: string|null
     * }>
     */
    public function photoDownloadProgress(array $flickrPhotoIds): array
    {
        return $this->photoPresenter->presentDownloadProgress($flickrPhotoIds);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function photosets(?string $ownerNsid, string $sort, string $direction, int $perPage, int $page): array
    {
        $paginator = $this->catalog->paginatePhotosets(
            $ownerNsid,
            $sort,
            $direction,
            $perPage,
            $page,
        );

        return [
            'data' => $this->collectionPresenter->presentPhotosets($paginator->items()),
            'meta' => $this->meta($paginator, $sort, $direction, CatalogQueryRepository::PHOTOSET_SORTS),
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function galleries(?string $ownerNsid, string $sort, string $direction, int $perPage, int $page): array
    {
        $paginator = $this->catalog->paginateGalleries(
            $ownerNsid,
            $sort,
            $direction,
            $perPage,
            $page,
        );

        return [
            'data' => $this->collectionPresenter->presentGalleries($paginator->items()),
            'meta' => $this->meta($paginator, $sort, $direction, CatalogQueryRepository::GALLERY_SORTS),
        ];
    }

    /**
     * @return array{data: list<mixed>, meta: array<string, mixed>}
     */
    public function favorites(?string $connectionKey, ?string $subjectNsid, string $sort, string $direction, int $perPage, int $page): array
    {
        $paginator = $this->catalog->paginateFavorites(
            $connectionKey,
            $subjectNsid,
            $sort,
            $direction,
            $perPage,
            $page,
        );

        return [
            'data' => $paginator->items(),
            'meta' => $this->meta($paginator, $sort, $direction, CatalogQueryRepository::FAVORITE_SORTS),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  list<string>  $allowedSorts
     * @return array<string, mixed>
     */
    private function meta(LengthAwarePaginator $paginator, string $sort, string $direction, array $allowedSorts): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'sort' => $this->sorter->resolveSort($sort, $allowedSorts),
            'direction' => $this->sorter->resolveDirection($direction),
        ];
    }
}

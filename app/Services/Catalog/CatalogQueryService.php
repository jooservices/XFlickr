<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Http\Requests\Api\Catalog\ListFavoritesRequest;
use App\Http\Requests\Api\Catalog\ListGalleriesRequest;
use App\Http\Requests\Api\Catalog\ListPhotosetsRequest;
use App\Http\Requests\Api\Catalog\ListPhotosRequest;
use App\Repositories\Crawler\CatalogQueryRepository;
use App\Support\Catalog\CollectionCatalogPresenter;
use App\Support\Catalog\PhotoCatalogPresenter;
use App\Support\Query\QuerySorter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

final class CatalogQueryService
{
    public function __construct(
        private readonly CatalogQueryRepository $catalog,
        private readonly QuerySorter $sorter,
        private readonly PhotoCatalogPresenter $photoPresenter,
        private readonly CollectionCatalogPresenter $collectionPresenter,
    ) {}

    public function photosResponse(ListPhotosRequest $request): JsonResponse
    {
        $paginator = $this->catalog->paginatePhotos(
            $request->ownerNsid(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->page(),
        );

        return response()->json([
            'data' => $this->photoPresenter->presentPage($paginator->items()),
            'meta' => $this->meta($paginator, $request->sort(), $request->direction(), CatalogQueryRepository::PHOTO_SORTS),
        ]);
    }

    public function photosetsResponse(ListPhotosetsRequest $request): JsonResponse
    {
        $paginator = $this->catalog->paginatePhotosets(
            $request->ownerNsid(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->page(),
        );

        return response()->json([
            'data' => $this->collectionPresenter->presentPhotosets($paginator->items()),
            'meta' => $this->meta($paginator, $request->sort(), $request->direction(), CatalogQueryRepository::PHOTOSET_SORTS),
        ]);
    }

    public function galleriesResponse(ListGalleriesRequest $request): JsonResponse
    {
        $paginator = $this->catalog->paginateGalleries(
            $request->ownerNsid(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->page(),
        );

        return response()->json([
            'data' => $this->collectionPresenter->presentGalleries($paginator->items()),
            'meta' => $this->meta($paginator, $request->sort(), $request->direction(), CatalogQueryRepository::GALLERY_SORTS),
        ]);
    }

    public function favoritesResponse(ListFavoritesRequest $request): JsonResponse
    {
        $paginator = $this->catalog->paginateFavorites(
            $request->connectionKey(),
            $request->subjectNsid(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->page(),
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => $this->meta($paginator, $request->sort(), $request->direction(), CatalogQueryRepository::FAVORITE_SORTS),
        ]);
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

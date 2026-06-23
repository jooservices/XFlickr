<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Catalog\ListFavoritesRequest;
use App\Http\Requests\Api\Catalog\ListGalleriesRequest;
use App\Http\Requests\Api\Catalog\ListPhotosetsRequest;
use App\Http\Requests\Api\Catalog\ListPhotosRequest;
use App\Services\Catalog\CatalogQueryService;
use Illuminate\Http\JsonResponse;

final class CatalogController
{
    public function __construct(
        private readonly CatalogQueryService $catalog,
    ) {}

    public function photos(ListPhotosRequest $request): JsonResponse
    {
        return $this->catalog->photosResponse($request);
    }

    public function photosets(ListPhotosetsRequest $request): JsonResponse
    {
        return $this->catalog->photosetsResponse($request);
    }

    public function galleries(ListGalleriesRequest $request): JsonResponse
    {
        return $this->catalog->galleriesResponse($request);
    }

    public function favorites(ListFavoritesRequest $request): JsonResponse
    {
        return $this->catalog->favoritesResponse($request);
    }
}

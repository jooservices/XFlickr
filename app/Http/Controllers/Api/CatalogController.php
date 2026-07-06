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
        return response()->json($this->catalog->photos(
            $request->ownerNsid(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->page(),
        ));
    }

    public function photosets(ListPhotosetsRequest $request): JsonResponse
    {
        return response()->json($this->catalog->photosets(
            $request->ownerNsid(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->page(),
        ));
    }

    public function galleries(ListGalleriesRequest $request): JsonResponse
    {
        return response()->json($this->catalog->galleries(
            $request->ownerNsid(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->page(),
        ));
    }

    public function favorites(ListFavoritesRequest $request): JsonResponse
    {
        return response()->json($this->catalog->favorites(
            $request->connectionKey(),
            $request->subjectNsid(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->page(),
        ));
    }
}

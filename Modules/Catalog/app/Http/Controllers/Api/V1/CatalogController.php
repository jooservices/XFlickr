<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Catalog\Http\Requests\Api\Catalog\ListFavoritesRequest;
use Modules\Catalog\Http\Requests\Api\Catalog\ListGalleriesRequest;
use Modules\Catalog\Http\Requests\Api\Catalog\ListPhotosetsRequest;
use Modules\Catalog\Http\Requests\Api\Catalog\ListPhotosRequest;
use Modules\Catalog\Http\Requests\Api\Catalog\PhotoDownloadProgressRequest;
use Modules\Catalog\Http\Resources\CatalogRowResource;
use Modules\Catalog\Http\Resources\PhotoDownloadProgressResource;
use Modules\Catalog\Services\CatalogQueryService;

final class CatalogController extends BaseApiController
{
    public function __construct(
        private readonly CatalogQueryService $catalog,
    ) {}

    public function photos(ListPhotosRequest $request): JsonResponse
    {
        return $this->successList($this->catalog->photos(
            $request->ownerNsid(),
            $request->photosetId(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->page(),
        ));
    }

    public function photosProgress(PhotoDownloadProgressRequest $request): JsonResponse
    {
        return $this->success(PhotoDownloadProgressResource::make([
            'photos' => $this->catalog->photoDownloadProgress($request->flickrPhotoIds()),
        ]));
    }

    public function showPhotoset(int $photoset): JsonResponse
    {
        $presented = $this->catalog->photoset($photoset);

        if ($presented === null) {
            return $this->notFound();
        }

        return $this->success(CatalogRowResource::make($presented));
    }

    public function photosets(ListPhotosetsRequest $request): JsonResponse
    {
        return $this->successList($this->catalog->photosets(
            $request->ownerNsid(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->page(),
        ));
    }

    public function galleries(ListGalleriesRequest $request): JsonResponse
    {
        return $this->successList($this->catalog->galleries(
            $request->ownerNsid(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->page(),
        ));
    }

    public function favorites(ListFavoritesRequest $request): JsonResponse
    {
        return $this->successList($this->catalog->favorites(
            $request->connectionKey(),
            $request->subjectNsid(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->page(),
        ));
    }

    /**
     * @param  array{data?: mixed, meta?: array<string, mixed>}  $payload
     */
    private function successList(array $payload): JsonResponse
    {
        $meta = $payload['meta'] ?? [];
        $rows = $payload['data'] ?? [];

        return $this->success(
            CatalogRowResource::collection(is_array($rows) || $rows instanceof Collection ? $rows : []),
            meta: is_array($meta) ? $meta : [],
        );
    }
}

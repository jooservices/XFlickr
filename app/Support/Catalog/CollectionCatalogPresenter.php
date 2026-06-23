<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use Illuminate\Support\Facades\DB;
use JOOservices\XFlickrCrawler\Models\Gallery;
use JOOservices\XFlickrCrawler\Models\Photoset;
use JOOservices\XFlickrCrawler\Support\XFlickrConfig;

final class CollectionCatalogPresenter
{
    /**
     * @param  iterable<int, Photoset>  $photosets
     * @return list<array<string, mixed>>
     */
    public function presentPhotosets(iterable $photosets): array
    {
        $items = collect($photosets)->values();
        if ($items->isEmpty()) {
            return [];
        }

        $fallbackThumbnails = $this->firstPhotoThumbnails(
            $items->pluck('id')->all(),
            XFlickrConfig::table('photoset_photo'),
            'xflickr_photoset_id',
        );

        return $items
            ->map(function (Photoset $photoset) use ($fallbackThumbnails): array {
                $data = $photoset->toArray();
                $thumbnail = $this->primaryPhotoFromRawPayload(
                    is_array($photoset->raw_payload) ? $photoset->raw_payload : null,
                    'photoset',
                ) ?? ($fallbackThumbnails[$photoset->id] ?? null);

                if ($thumbnail !== null) {
                    $data = array_merge($data, $thumbnail);
                }

                return $data;
            })
            ->all();
    }

    /**
     * @param  iterable<int, Gallery>  $galleries
     * @return list<array<string, mixed>>
     */
    public function presentGalleries(iterable $galleries): array
    {
        $items = collect($galleries)->values();
        if ($items->isEmpty()) {
            return [];
        }

        $fallbackThumbnails = $this->firstPhotoThumbnails(
            $items->pluck('id')->all(),
            XFlickrConfig::table('gallery_photo'),
            'xflickr_gallery_id',
        );

        return $items
            ->map(function (Gallery $gallery) use ($fallbackThumbnails): array {
                $data = $gallery->toArray();
                $thumbnail = $this->primaryPhotoFromRawPayload(
                    is_array($gallery->raw_payload) ? $gallery->raw_payload : null,
                    'gallery',
                ) ?? ($fallbackThumbnails[$gallery->id] ?? null);

                if ($thumbnail !== null) {
                    $data = array_merge($data, $thumbnail);
                }

                return $data;
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $rawPayload
     * @return array{primary_photo_id: string, primary_secret: string, primary_server: string}|null
     */
    private function primaryPhotoFromRawPayload(?array $rawPayload, string $kind): ?array
    {
        if ($rawPayload === null) {
            return null;
        }

        if ($kind === 'photoset') {
            $photoId = $this->stringValue($rawPayload['primary'] ?? null);
            $secret = $this->stringValue($rawPayload['secret'] ?? null);
            $server = $this->stringValue($rawPayload['server'] ?? null);
        } else {
            $photoId = $this->stringValue($rawPayload['primary_photo_id'] ?? $rawPayload['primary'] ?? null);
            $secret = $this->stringValue($rawPayload['primary_photo_secret'] ?? $rawPayload['secret'] ?? null);
            $server = $this->stringValue($rawPayload['primary_photo_server'] ?? $rawPayload['server'] ?? null);
        }

        if ($photoId === null || $secret === null || $server === null) {
            return null;
        }

        return [
            'primary_photo_id' => $photoId,
            'primary_secret' => $secret,
            'primary_server' => $server,
        ];
    }

    /**
     * @param  list<int>  $collectionIds
     * @return array<int, array{primary_photo_id: string, primary_secret: string, primary_server: string}>
     */
    private function firstPhotoThumbnails(array $collectionIds, string $pivotTable, string $pivotParentColumn): array
    {
        if ($collectionIds === []) {
            return [];
        }

        $photosTable = XFlickrConfig::table('photos');
        $rows = DB::table($pivotTable)
            ->join($photosTable, "{$pivotTable}.xflickr_photo_id", '=', "{$photosTable}.id")
            ->whereIn("{$pivotTable}.{$pivotParentColumn}", $collectionIds)
            ->orderBy("{$pivotTable}.discovered_at")
            ->get([
                "{$pivotTable}.{$pivotParentColumn} as collection_id",
                "{$photosTable}.flickr_photo_id",
                "{$photosTable}.secret",
                "{$photosTable}.server",
            ]);

        /** @var array<int, array{primary_photo_id: string, primary_secret: string, primary_server: string}> $thumbnails */
        $thumbnails = [];

        foreach ($rows as $row) {
            $collectionId = (int) $row->collection_id;
            if (isset($thumbnails[$collectionId])) {
                continue;
            }

            $photoId = is_string($row->flickr_photo_id) ? $row->flickr_photo_id : '';
            $secret = is_string($row->secret) ? $row->secret : '';
            $server = is_string($row->server) ? $row->server : '';

            if ($photoId === '' || $secret === '' || $server === '') {
                continue;
            }

            $thumbnails[$collectionId] = [
                'primary_photo_id' => $photoId,
                'primary_secret' => $secret,
                'primary_server' => $server,
            ];
        }

        return $thumbnails;
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

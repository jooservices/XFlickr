<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use Illuminate\Support\Facades\DB;
use JOOservices\XFlickrCrawler\Models\Photo;
use JOOservices\XFlickrCrawler\Support\XFlickrConfig;

final class PhotoCatalogPresenter
{
    /**
     * @param  iterable<int, Photo>  $photos
     * @return list<array<string, mixed>>
     */
    public function presentPage(iterable $photos): array
    {
        $items = collect($photos)->values();
        if ($items->isEmpty()) {
            return [];
        }

        $photoIds = $items->pluck('id')->all();
        $photosetsByPhotoId = $this->membershipsForPhotoIds(
            $photoIds,
            XFlickrConfig::table('photoset_photo'),
            'xflickr_photoset_id',
            XFlickrConfig::table('photosets'),
            'flickr_photoset_id',
        );
        $galleriesByPhotoId = $this->membershipsForPhotoIds(
            $photoIds,
            XFlickrConfig::table('gallery_photo'),
            'xflickr_gallery_id',
            XFlickrConfig::table('galleries'),
            'flickr_gallery_id',
        );

        return $items
            ->map(function (Photo $photo) use ($photosetsByPhotoId, $galleriesByPhotoId): array {
                $data = $photo->toArray();
                $data['photosets'] = $photosetsByPhotoId[$photo->id] ?? [];
                $data['galleries'] = $galleriesByPhotoId[$photo->id] ?? [];

                return $data;
            })
            ->all();
    }

    /**
     * @param  list<int>  $photoIds
     * @return array<int, list<array{flickr_id: string, owner_nsid: string, title: string|null}>>
     */
    private function membershipsForPhotoIds(
        array $photoIds,
        string $pivotTable,
        string $pivotParentColumn,
        string $parentTable,
        string $parentFlickrIdColumn,
    ): array {
        if ($photoIds === []) {
            return [];
        }

        $rows = DB::table($pivotTable)
            ->join(
                $parentTable,
                "{$pivotTable}.{$pivotParentColumn}",
                '=',
                "{$parentTable}.id",
            )
            ->whereIn("{$pivotTable}.xflickr_photo_id", $photoIds)
            ->orderBy("{$parentTable}.title")
            ->get([
                "{$pivotTable}.xflickr_photo_id as photo_id",
                "{$parentTable}.{$parentFlickrIdColumn} as flickr_id",
                "{$parentTable}.owner_nsid",
                "{$parentTable}.title",
            ]);

        /** @var array<int, list<array{flickr_id: string, owner_nsid: string, title: string|null}>> $grouped */
        $grouped = [];

        foreach ($rows as $row) {
            $photoId = (int) $row->photo_id;
            $grouped[$photoId] ??= [];
            $grouped[$photoId][] = [
                'flickr_id' => (string) $row->flickr_id,
                'owner_nsid' => (string) $row->owner_nsid,
                'title' => is_string($row->title) ? $row->title : null,
            ];
        }

        return $grouped;
    }
}

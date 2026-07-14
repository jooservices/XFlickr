<?php

declare(strict_types=1);

namespace Modules\Catalog\Support;

use Illuminate\Support\Facades\DB;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Support\XFlickrConfig;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Repositories\StoredFileRepository;

final class PhotoCatalogPresenter
{
    public function __construct(
        private readonly StoredFileRepository $storedFiles,
    ) {}

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
        $storedByFlickrPhotoId = $this->storedFiles->originalsByFlickrPhotoIds(
            $items->pluck('flickr_photo_id')->all(),
        );

        return $items
            ->map(function (Photo $photo) use ($photosetsByPhotoId, $galleriesByPhotoId, $storedByFlickrPhotoId): array {
                $data = $photo->toArray();
                $data['photosets'] = $photosetsByPhotoId[$photo->id] ?? [];
                $data['galleries'] = $galleriesByPhotoId[$photo->id] ?? [];
                $flickrPhotoId = $data['flickr_photo_id'] ?? null;
                $data = array_merge($data, $this->presentDownloadMeta(
                    is_string($flickrPhotoId) ? $storedByFlickrPhotoId->get($flickrPhotoId) : null,
                ));

                return $data;
            })
            ->all();
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
    public function presentDownloadProgress(array $flickrPhotoIds): array
    {
        $ids = array_values(array_unique(array_filter(
            $flickrPhotoIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($ids === []) {
            return [];
        }

        $storedByFlickrPhotoId = $this->storedFiles->originalsByFlickrPhotoIds($ids);

        return array_map(
            function (string $flickrPhotoId) use ($storedByFlickrPhotoId): array {
                return array_merge(
                    ['flickr_photo_id' => $flickrPhotoId],
                    $this->presentDownloadMeta($storedByFlickrPhotoId->get($flickrPhotoId)),
                );
            },
            $ids,
        );
    }

    /**
     * @return array{download_status: string, stored_file_uuid: string|null, stored_file_view_url: string|null}
     */
    private function presentDownloadMeta(?StoredFile $stored): array
    {
        if ($stored === null) {
            return [
                'download_status' => 'none',
                'stored_file_uuid' => null,
                'stored_file_view_url' => null,
            ];
        }

        $viewUrl = $stored->status === StoredFileStatus::Completed->value && is_string($stored->uuid) && $stored->uuid !== ''
            ? url('/api/v1/stored-files/'.$stored->uuid)
            : null;

        return [
            'download_status' => $stored->status,
            'stored_file_uuid' => $stored->uuid,
            'stored_file_view_url' => $viewUrl,
        ];
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

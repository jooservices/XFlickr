<?php

declare(strict_types=1);

namespace Modules\Crawler\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Crawler\Models\Gallery;

final class GalleryRepository
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function upsertMany(array $rows, ?int $chunk = null): int
    {
        if ($rows === []) {
            return 0;
        }

        $chunkSize = $chunk ?? (int) config('xflickr-crawler.bulk.chunk_size', 250);
        $table = (new Gallery)->getTable();
        $now = now();
        $total = 0;

        foreach (array_chunk($rows, $chunkSize) as $batch) {
            $payload = [];
            foreach ($batch as $row) {
                $payload[] = array_merge($row, [
                    'updated_at' => $now,
                    'created_at' => $now,
                ]);
            }

            DB::table($table)->upsert(
                $payload,
                ['flickr_gallery_id'],
                ['owner_nsid', 'title', 'description', 'photo_count', 'raw_payload', 'updated_at'],
            );
            $total += count($batch);
        }

        return $total;
    }

    /**
     * @param  list<string>  $flickrGalleryIds
     * @return array<string, int> flickr_gallery_id => internal id
     */
    public function idsByFlickrGalleryIds(array $flickrGalleryIds): array
    {
        if ($flickrGalleryIds === []) {
            return [];
        }

        return Gallery::query()
            ->whereIn('flickr_gallery_id', $flickrGalleryIds)
            ->pluck('id', 'flickr_gallery_id')
            ->all();
    }

    public function countByOwnerNsid(string $ownerNsid): int
    {
        return Gallery::query()->where('owner_nsid', $ownerNsid)->count();
    }
}

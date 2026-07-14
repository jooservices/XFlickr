<?php

declare(strict_types=1);

namespace Modules\Crawler\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Crawler\Support\XFlickrConfig;

final class PivotRepository
{
    /**
     * @param  list<array{xflickr_photoset_id: int, xflickr_photo_id: int, discovered_at: string}>  $rows
     */
    public function attachPhotosetPhotos(array $rows, ?int $chunk = null): void
    {
        if ($rows === []) {
            return;
        }

        $chunkSize = $chunk ?? 500;
        $table = XFlickrConfig::table('photoset_photo');

        foreach (array_chunk($rows, $chunkSize) as $batch) {
            DB::table($table)->insertOrIgnore($batch);
        }
    }

    /**
     * @param  list<array{xflickr_gallery_id: int, xflickr_photo_id: int, discovered_at: string}>  $rows
     */
    public function attachGalleryPhotos(array $rows, ?int $chunk = null): void
    {
        if ($rows === []) {
            return;
        }

        $chunkSize = $chunk ?? 500;
        $table = XFlickrConfig::table('gallery_photo');

        foreach (array_chunk($rows, $chunkSize) as $batch) {
            DB::table($table)->insertOrIgnore($batch);
        }
    }
}

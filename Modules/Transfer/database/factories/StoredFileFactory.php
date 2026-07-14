<?php

declare(strict_types=1);

namespace Modules\Transfer\Database\Factories;

use Database\Factories\Concerns\GeneratesFlickrNsid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Transfer\Models\StoredFile;

/** @extends Factory<StoredFile> */
class StoredFileFactory extends Factory
{
    use GeneratesFlickrNsid;

    protected $model = StoredFile::class;

    public function definition(): array
    {
        $photoId = (string) fake()->unique()->numerify('#########');

        return [
            'uuid' => (string) Str::uuid(),
            'flickr_photo_id' => $photoId,
            'owner_nsid' => $this->flickrNsid(),
            'variant' => 'Original',
            'local_path' => 'downloads/'.fake()->uuid().'.jpg',
            'original_name' => fake()->lexify('photo-????.jpg'),
            'mime_type' => 'image/jpeg',
            'bytes' => fake()->numberBetween(1_000, 5_000_000),
            'status' => 'ready',
            'dedup_key' => "flickr:{$photoId}:Original",
            'content_sha256' => fake()->sha256(),
            'metadata' => [],
            'error_message' => null,
            'downloaded_at' => now(),
        ];
    }
}

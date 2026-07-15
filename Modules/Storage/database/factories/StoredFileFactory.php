<?php

declare(strict_types=1);

namespace Modules\Storage\Database\Factories;

use Database\Factories\Concerns\GeneratesFlickrNsid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Storage\Models\StoredFile;

/** @extends Factory<StoredFile> */
class StoredFileFactory extends Factory
{
    use GeneratesFlickrNsid;

    protected $model = StoredFile::class;

    public function definition(): array
    {
        $sourceId = (string) fake()->unique()->numerify('#########');

        return [
            'uuid' => (string) Str::uuid(),
            'source_type' => 'flickr_photo',
            'source_id' => $sourceId,
            'source_owner' => $this->flickrNsid(),
            'variant' => 'Original',
            'local_path' => 'downloads/'.fake()->uuid().'.jpg',
            'original_name' => fake()->lexify('photo-????.jpg'),
            'mime_type' => 'image/jpeg',
            'bytes' => fake()->numberBetween(1_000, 5_000_000),
            'status' => 'ready',
            'dedup_key' => "flickr_photo:{$sourceId}:Original",
            'content_sha256' => fake()->sha256(),
            'metadata' => [],
            'error_message' => null,
            'downloaded_at' => now(),
        ];
    }
}

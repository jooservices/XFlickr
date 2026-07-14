<?php

declare(strict_types=1);

namespace Modules\Storage\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageRemoteAlbum;

/** @extends Factory<StorageRemoteAlbum> */
class StorageRemoteAlbumFactory extends Factory
{
    protected $model = StorageRemoteAlbum::class;

    public function definition(): array
    {
        return [
            'storage_account_id' => StorageAccount::factory(),
            'parent_remote_id' => '',
            'remote_id' => fake()->uuid(),
            'title' => fake()->words(3, true),
            'cover_thumbnail_url' => fake()->url(),
            'media_items_count' => fake()->numberBetween(0, 100),
            'synced_at' => now(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Storage\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageRemoteItem;

/** @extends Factory<StorageRemoteItem> */
class StorageRemoteItemFactory extends Factory
{
    protected $model = StorageRemoteItem::class;

    public function definition(): array
    {
        return [
            'storage_account_id' => StorageAccount::factory(),
            'parent_remote_id' => '',
            'remote_id' => fake()->uuid(),
            'name' => fake()->lexify('file-????.jpg'),
            'mime_type' => 'image/jpeg',
            'thumbnail_url' => fake()->url(),
            'size' => fake()->numberBetween(1000, 9_000_000),
            'modified_at' => now(),
            'web_url' => fake()->url(),
            'synced_at' => now(),
        ];
    }
}

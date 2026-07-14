<?php

declare(strict_types=1);

namespace Modules\Storage\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageRemoteSyncState;

/** @extends Factory<StorageRemoteSyncState> */
class StorageRemoteSyncStateFactory extends Factory
{
    protected $model = StorageRemoteSyncState::class;

    public function definition(): array
    {
        return [
            'storage_account_id' => StorageAccount::factory(),
            'parent_remote_id' => '',
            'album_page_token' => null,
            'item_page_token' => null,
            'albums_complete' => false,
            'items_complete' => false,
            'reconciling' => false,
            'reconcile_snapshot' => null,
            'reconcile_seen_remote_ids' => null,
            'last_synced_at' => null,
        ];
    }
}

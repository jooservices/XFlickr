<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageRemoteAlbum;
use Modules\Storage\Models\StorageRemoteItem;
use Modules\Storage\Models\StorageRemoteSyncState;
use Modules\Storage\Services\StorageBrowseLocalService;
use Modules\Storage\Tests\TestCase;

final class StorageBrowseLocalServiceTest extends TestCase
{
    private StorageBrowseLocalService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StorageBrowseLocalService::class);
    }

    public function test_browse_root_includes_albums_and_items(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $album = StorageRemoteAlbum::factory()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
        ]);
        $item = StorageRemoteItem::factory()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
        ]);
        StorageRemoteSyncState::factory()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
            'albums_complete' => true,
            'items_complete' => false,
            'last_synced_at' => now(),
        ]);

        $result = $this->service->browse($account, null, 1, 1, 25);

        $this->assertCount(1, $result->albums);
        $this->assertSame($album->remote_id, $result->albums[0]['id']);
        $this->assertCount(1, $result->items);
        $this->assertSame($item->remote_id, $result->items[0]['id']);
        $this->assertSame('local', $result->localMeta['source']);
        $this->assertTrue($result->localMeta['sync_has_more']);
    }

    public function test_browse_nested_omits_albums(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $containerId = fake()->uuid();
        StorageRemoteAlbum::factory()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
        ]);
        StorageRemoteItem::factory()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => $containerId,
        ]);

        $result = $this->service->browse($account, $containerId, 1, 1, 25);

        $this->assertSame([], $result->albums);
        $this->assertCount(1, $result->items);
    }

    public function test_wipe_cache_for_root_deletes_items_and_albums(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        StorageRemoteAlbum::factory()->create(['storage_account_id' => $account->id]);
        StorageRemoteItem::factory()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
        ]);

        $this->service->wipeCacheForParent($account, null);

        $this->assertDatabaseMissing('storage_remote_albums', ['storage_account_id' => $account->id]);
        $this->assertDatabaseMissing('storage_remote_items', ['storage_account_id' => $account->id]);
    }

    public function test_wipe_cache_for_child_keeps_albums(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $containerId = fake()->uuid();
        $album = StorageRemoteAlbum::factory()->create(['storage_account_id' => $account->id]);
        StorageRemoteItem::factory()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => $containerId,
        ]);

        $this->service->wipeCacheForParent($account, $containerId);

        $this->assertDatabaseHas('storage_remote_albums', ['id' => $album->id]);
        $this->assertDatabaseMissing('storage_remote_items', [
            'storage_account_id' => $account->id,
            'parent_remote_id' => $containerId,
        ]);
    }

    public function test_empty_string_container_matches_root_parent_key(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $item = StorageRemoteItem::factory()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
        ]);

        $ids = $this->service->snapshotRemoteIdsForParent($account, '');

        $this->assertSame([$item->remote_id], $ids);
    }
}

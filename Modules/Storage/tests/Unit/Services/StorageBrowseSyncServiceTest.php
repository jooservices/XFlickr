<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageRemoteItem;
use Modules\Storage\Models\StorageRemoteSyncState;
use Modules\Storage\Services\StorageBrowseSyncService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageBrowseSyncServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_reset_deletes_sync_state_for_parent(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        StorageRemoteSyncState::factory()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
        ]);

        app(StorageBrowseSyncService::class)->reset($account);

        $this->assertDatabaseMissing('storage_remote_sync_states', [
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
        ]);
    }

    public function test_sync_returns_zero_counts_when_already_complete(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        StorageRemoteSyncState::factory()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
            'albums_complete' => true,
            'items_complete' => true,
            'last_synced_at' => now(),
        ]);

        Http::fake();

        $result = app(StorageBrowseSyncService::class)->sync($account, StorageDriver::GooglePhotos);

        $this->assertSame(0, $result['albums_synced']);
        $this->assertSame(0, $result['items_synced']);
        $this->assertFalse($result['has_more']);
        Http::assertNothingSent();
    }

    public function test_reconcile_wipes_cache_and_marks_state_reconciling(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $remoteId = fake()->uuid();

        StorageRemoteItem::factory()->create([
            'storage_account_id' => $account->id,
            'remote_id' => $remoteId,
            'parent_remote_id' => '',
        ]);

        app(StorageBrowseSyncService::class)->reconcile($account);

        $this->assertDatabaseMissing('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => $remoteId,
        ]);
        $this->assertDatabaseHas('storage_remote_sync_states', [
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
            'reconciling' => true,
            'albums_complete' => false,
            'items_complete' => false,
        ]);
    }

    public function test_sync_persists_albums_and_items_from_browse_result(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $albumId = fake()->uuid();
        $itemId = fake()->uuid();

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response([
                'albums' => [[
                    'id' => $albumId,
                    'title' => fake()->words(2, true),
                    'mediaItemsCount' => 3,
                ]],
            ]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response([
                'mediaItems' => [[
                    'id' => $itemId,
                    'filename' => fake()->word().'.jpg',
                    'mimeType' => 'image/jpeg',
                    'productUrl' => 'https://photos.google.com/lr/photo/'.$itemId,
                    'mediaMetadata' => ['creationTime' => now()->toIso8601String()],
                ]],
            ]),
        ]);

        $result = app(StorageBrowseSyncService::class)->sync($account, StorageDriver::GooglePhotos, maxBatches: 1);

        $this->assertSame(1, $result['albums_synced']);
        $this->assertSame(1, $result['items_synced']);
        $this->assertDatabaseHas('storage_remote_albums', [
            'storage_account_id' => $account->id,
            'remote_id' => $albumId,
        ]);
        $this->assertDatabaseHas('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => $itemId,
        ]);
    }
}

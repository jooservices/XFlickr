<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Feature\Controllers\Api\V1;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageRemoteItem;
use Modules\Transfer\Models\StorageUpload;
use Modules\Transfer\Models\StoredFile;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageDeleteTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_google_photos_driver_includes_edit_scope(): void
    {
        $scopes = StorageDriver::GooglePhotos->defaultScopes();

        $this->assertContains('https://www.googleapis.com/auth/photoslibrary.edit.appcreateddata', $scopes);
    }

    public function test_storage_delete_requires_account_id(): void
    {
        $response = $this->deleteJson('/api/v1/storage/google-photos/files', [
            'item_ids' => ['media-1'],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'account_id is required.']);
    }

    public function test_storage_delete_requires_item_ids(): void
    {
        $account = $this->googlePhotosAccount();

        $response = $this->deleteJson('/api/v1/storage/google-photos/files', [
            'account_id' => $account->id,
            'item_ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_storage_delete_blocks_accounts_missing_scopes(): void
    {
        $account = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Needs reauth',
            'credentials' => [
                'access_token' => 'token',
                'granted_scopes' => ['https://www.googleapis.com/auth/photoslibrary.appendonly'],
            ],
            'connected_at' => now(),
        ]);

        $response = $this->deleteJson('/api/v1/storage/google-photos/files', [
            'account_id' => $account->id,
            'item_ids' => ['media-1'],
            'container_id' => 'album-1',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('data.needs_reauthorization', true);
    }

    public function test_google_photos_delete_requires_container_id(): void
    {
        $account = $this->googlePhotosAccount();

        $response = $this->deleteJson('/api/v1/storage/google-photos/files', [
            'account_id' => $account->id,
            'item_ids' => ['media-1'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath(
            'message',
            'Google Photos delete requires an album. Open an album and remove items from it.',
        );
    }

    public function test_google_photos_delete_removes_items_from_album_and_local_cache(): void
    {
        $account = $this->googlePhotosAccount();

        StorageRemoteItem::query()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => 'album-1',
            'remote_id' => 'media-1',
            'name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'modified_at' => now(),
            'synced_at' => now(),
        ]);

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums/album-1:batchRemoveMediaItems' => Http::response(null, 200),
        ]);

        $response = $this->deleteJson('/api/v1/storage/google-photos/files', [
            'account_id' => $account->id,
            'item_ids' => ['media-1'],
            'container_id' => 'album-1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted', ['media-1']);
        $response->assertJsonPath('data.failed', []);

        $this->assertDatabaseMissing('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => 'media-1',
        ]);
    }

    public function test_google_photos_delete_keeps_storage_uploads(): void
    {
        $account = $this->googlePhotosAccount();

        $storedFile = StoredFile::query()->create([
            'uuid' => (string) Str::uuid(),
            'source_id' => 'photo-1',
            'source_owner' => 'nsid-1',
            'original_name' => 'photo.jpg',
            'status' => 'completed',
        ]);

        StorageUpload::query()->create([
            'stored_file_id' => $storedFile->id,
            'storage_account_id' => $account->id,
            'remote_file_id' => 'media-1',
            'remote_path' => 'media-1',
            'status' => 'completed',
            'uploaded_at' => now(),
        ]);

        StorageRemoteItem::query()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => 'album-1',
            'remote_id' => 'media-1',
            'name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'modified_at' => now(),
            'synced_at' => now(),
        ]);

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums/album-1:batchRemoveMediaItems' => Http::response(null, 200),
        ]);

        $response = $this->deleteJson('/api/v1/storage/google-photos/files', [
            'account_id' => $account->id,
            'item_ids' => ['media-1'],
            'container_id' => 'album-1',
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => 'media-1',
        ]);

        $this->assertDatabaseHas('storage_uploads', [
            'storage_account_id' => $account->id,
            'remote_file_id' => 'media-1',
            'status' => 'completed',
        ]);
    }

    public function test_onedrive_delete_removes_local_cache(): void
    {
        $account = StorageAccount::query()->create([
            'provider' => 'onedrive',
            'label' => 'OneDrive account',
            'credentials' => [
                'access_token' => 'test-token',
                'expires_at' => now()->addHour()->toIso8601String(),
                'granted_scopes' => StorageDriver::OneDrive->defaultScopes(),
            ],
            'connected_at' => now(),
        ]);

        StorageRemoteItem::query()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
            'remote_id' => 'item-1',
            'name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'modified_at' => now(),
            'synced_at' => now(),
        ]);

        $storedFile = StoredFile::query()->create([
            'uuid' => (string) Str::uuid(),
            'source_id' => 'photo-2',
            'source_owner' => 'nsid-2',
            'original_name' => 'photo.jpg',
            'status' => 'completed',
        ]);

        StorageUpload::query()->create([
            'stored_file_id' => $storedFile->id,
            'storage_account_id' => $account->id,
            'remote_file_id' => 'item-1',
            'remote_path' => 'item-1',
            'status' => 'completed',
            'uploaded_at' => now(),
        ]);

        Http::fake([
            'graph.microsoft.com/v1.0/me/drive/items/item-1' => Http::response(null, 204),
        ]);

        $response = $this->deleteJson('/api/v1/storage/onedrive/files', [
            'account_id' => $account->id,
            'item_ids' => ['item-1'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted', ['item-1']);

        $this->assertDatabaseMissing('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => 'item-1',
        ]);

        $this->assertDatabaseMissing('storage_uploads', [
            'storage_account_id' => $account->id,
            'remote_file_id' => 'item-1',
        ]);
    }

    public function test_google_photos_browse_uses_product_url_when_available(): void
    {
        $account = $this->googlePhotosAccount();

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response(['albums' => []]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response([
                'mediaItems' => [
                    [
                        'id' => 'media-1',
                        'filename' => 'photo.jpg',
                        'mimeType' => 'image/jpeg',
                        'baseUrl' => 'https://example.com/photo',
                        'productUrl' => 'https://photos.google.com/lr/photo/abc',
                        'mediaMetadata' => ['creationTime' => '2026-01-01T00:00:00Z'],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson("/api/v1/storage/google-photos/files?account_id={$account->id}&source=provider");

        $response->assertOk();
        $response->assertJsonPath('data.items.0.web_url', 'https://photos.google.com/lr/photo/abc');
    }

    private function googlePhotosAccount(): StorageAccount
    {
        return StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Photos account',
            'credentials' => [
                'access_token' => 'test-token',
                'refresh_token' => 'refresh',
                'client_id' => 'client',
                'client_secret' => 'secret',
                'expires_at' => now()->addHour()->toIso8601String(),
                'granted_scopes' => StorageDriver::GooglePhotos->defaultScopes(),
            ],
            'connected_at' => now(),
        ]);
    }
}

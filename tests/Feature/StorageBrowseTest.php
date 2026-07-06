<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StorageDriver;
use App\Models\StorageAccount;
use App\Models\StorageRemoteItem;
use App\Services\Storage\StorageAccountScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class StorageBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_photos_driver_includes_read_scope(): void
    {
        $scopes = StorageDriver::GooglePhotos->defaultScopes();

        $this->assertContains('https://www.googleapis.com/auth/photoslibrary.appendonly', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/photoslibrary.readonly.appcreateddata', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/photoslibrary.edit.appcreateddata', $scopes);
    }

    public function test_legacy_account_without_granted_scopes_needs_reauthorization(): void
    {
        $account = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Legacy account',
            'credentials' => [
                'access_token' => 'token',
                'refresh_token' => 'refresh',
            ],
            'connected_at' => now(),
        ]);

        $service = app(StorageAccountScopeService::class);

        $this->assertTrue($service->needsReauthorization($account));
        $this->assertNotEmpty($service->missingScopes($account));
    }

    public function test_account_with_current_scopes_does_not_need_reauthorization(): void
    {
        $account = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Current account',
            'credentials' => [
                'access_token' => 'token',
                'granted_scopes' => StorageDriver::GooglePhotos->defaultScopes(),
            ],
            'connected_at' => now(),
        ]);

        $service = app(StorageAccountScopeService::class);

        $this->assertFalse($service->needsReauthorization($account));
    }

    public function test_storage_browse_requires_account_id(): void
    {
        $response = $this->getJson('/api/storage/google-photos/browse');

        $response->assertStatus(422);
        $response->assertJson(['message' => 'account_id is required.']);
    }

    public function test_storage_browse_blocks_accounts_missing_scopes(): void
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

        $response = $this->getJson("/api/storage/google-photos/browse?account_id={$account->id}");

        $response->assertStatus(403);
        $response->assertJsonPath('needs_reauthorization', true);
        $response->assertJsonStructure(['reauthorize_url', 'missing_scopes']);
    }

    public function test_google_photos_thumbnail_rejects_untrusted_base_url(): void
    {
        $account = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Test Photos',
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

        Http::fake([
            'photoslibrary.googleapis.com/v1/mediaItems/media-1' => Http::response([
                'baseUrl' => 'http://169.254.169.254/latest/meta-data',
            ]),
            '169.254.169.254/*' => Http::response('metadata', 200),
        ]);

        $response = $this->getJson("/api/storage/google-photos/thumbnail?account_id={$account->id}&media_id=media-1");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Unable to load thumbnail.']);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '169.254.169.254'));
    }

    public function test_storage_browse_lists_google_photos_albums_and_items(): void
    {
        $account = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Test Photos',
            'credentials' => [
                'access_token' => 'test-token',
                'refresh_token' => 'refresh',
                'client_id' => 'client',
                'client_secret' => 'secret',
                'expires_at' => now()->addHour()->toIso8601String(),
                'granted_scopes' => StorageDriver::GooglePhotos->defaultScopes(),
            ],
            'is_default' => true,
            'connected_at' => now(),
        ]);

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response([
                'albums' => [
                    [
                        'id' => 'album-1',
                        'title' => 'XFlickr Album',
                        'coverPhotoBaseUrl' => 'https://example.com/cover',
                        'mediaItemsCount' => 3,
                    ],
                ],
            ]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::sequence()
                ->push(['mediaItems' => [], 'nextPageToken' => 'page-2'])
                ->push([
                    'mediaItems' => [
                        [
                            'id' => 'media-1',
                            'filename' => 'photo.jpg',
                            'mimeType' => 'image/jpeg',
                            'baseUrl' => 'https://example.com/photo',
                            'mediaMetadata' => ['creationTime' => '2026-01-01T00:00:00Z'],
                        ],
                    ],
                ]),
        ]);

        $response = $this->getJson("/api/storage/google-photos/browse?account_id={$account->id}&source=provider");

        $response->assertOk();
        $response->assertJsonPath('albums.0.title', 'XFlickr Album');
        $response->assertJsonPath('items.0.name', 'photo.jpg');
        $response->assertJsonPath('meta.per_page', 25);
    }

    public function test_local_browse_returns_cached_items(): void
    {
        $account = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Cached Photos',
            'credentials' => [
                'access_token' => 'test-token',
                'granted_scopes' => StorageDriver::GooglePhotos->defaultScopes(),
            ],
            'connected_at' => now(),
        ]);

        StorageRemoteItem::query()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
            'remote_id' => 'cached-media-1',
            'name' => 'cached.jpg',
            'mime_type' => 'image/jpeg',
            'thumbnail_url' => 'https://example.com/thumb',
            'modified_at' => now(),
            'synced_at' => now(),
        ]);

        $response = $this->getJson("/api/storage/google-photos/browse?account_id={$account->id}");

        $response->assertOk();
        $response->assertJsonPath('items.0.name', 'cached.jpg');
        $response->assertJsonPath('meta.source', 'local');
        $response->assertJsonPath('meta.item_total', 1);
    }

    public function test_sync_persists_provider_items_to_local_cache(): void
    {
        $account = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Sync Photos',
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

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response(['albums' => []]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response([
                'mediaItems' => [
                    [
                        'id' => 'synced-media-1',
                        'filename' => 'synced.jpg',
                        'mimeType' => 'image/jpeg',
                        'baseUrl' => 'https://example.com/photo',
                        'mediaMetadata' => ['creationTime' => '2026-01-01T00:00:00Z'],
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson("/api/storage/google-photos/sync?account_id={$account->id}");

        $response->assertOk();
        $response->assertJsonPath('data.items_synced', 1);

        $this->assertDatabaseHas('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => 'synced-media-1',
            'name' => 'synced.jpg',
        ]);

        $browse = $this->getJson("/api/storage/google-photos/browse?account_id={$account->id}");
        $browse->assertOk();
        $browse->assertJsonPath('items.0.name', 'synced.jpg');
    }

    public function test_storage_accounts_endpoint_includes_reauthorization_meta(): void
    {
        StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Photos account',
            'credentials' => ['access_token' => 'token'],
            'connected_at' => now(),
        ]);

        $response = $this->getJson('/api/storage/accounts?provider=google_photos');

        $response->assertOk();
        $response->assertJsonPath('data.0.needs_reauthorization', true);
        $response->assertJsonStructure([
            'data' => [
                [
                    'reauthorize_url',
                    'missing_scopes',
                ],
            ],
        ]);
    }
}

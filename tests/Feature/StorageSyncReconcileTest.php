<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StorageDriver;
use App\Models\StorageAccount;
use App\Models\StorageRemoteItem;
use App\Models\StorageUpload;
use App\Models\StoredFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StorageSyncReconcileTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_sync_with_reconcile_drops_missing_items_and_uploads(): void
    {
        $account = $this->googlePhotosAccount();

        StorageRemoteItem::query()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
            'remote_id' => 'media-removed',
            'name' => 'removed.jpg',
            'mime_type' => 'image/jpeg',
            'modified_at' => now(),
            'synced_at' => now(),
        ]);

        StorageRemoteItem::query()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
            'remote_id' => 'media-kept',
            'name' => 'kept.jpg',
            'mime_type' => 'image/jpeg',
            'modified_at' => now(),
            'synced_at' => now(),
        ]);

        $storedFile = StoredFile::query()->create([
            'uuid' => (string) Str::uuid(),
            'flickr_photo_id' => 'photo-removed',
            'owner_nsid' => 'nsid-1',
            'original_name' => 'removed.jpg',
            'status' => 'completed',
        ]);

        StorageUpload::query()->create([
            'stored_file_id' => $storedFile->id,
            'storage_account_id' => $account->id,
            'remote_file_id' => 'media-removed',
            'remote_path' => 'media-removed',
            'status' => 'completed',
            'uploaded_at' => now(),
        ]);

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response(['albums' => []]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response([
                'mediaItems' => [
                    [
                        'id' => 'media-kept',
                        'filename' => 'kept.jpg',
                        'mimeType' => 'image/jpeg',
                        'productUrl' => 'https://photos.google.com/lr/photo/kept',
                        'mediaMetadata' => ['creationTime' => '2026-01-01T00:00:00Z'],
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson("/api/storage/google-photos/sync?account_id={$account->id}", [
            'reconcile' => true,
            'max_batches' => 10,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.items_complete', true);

        $this->assertDatabaseMissing('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => 'media-removed',
        ]);

        $this->assertDatabaseHas('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => 'media-kept',
        ]);

        $this->assertDatabaseMissing('storage_uploads', [
            'storage_account_id' => $account->id,
            'remote_file_id' => 'media-removed',
        ]);
    }

    public function test_multi_page_sync_advances_tokens_across_separate_sync_calls(): void
    {
        $account = $this->googlePhotosAccount();

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response(['albums' => []]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::sequence()
                ->push([
                    'mediaItems' => [
                        [
                            'id' => 'media-page-1',
                            'filename' => 'page1.jpg',
                            'mimeType' => 'image/jpeg',
                            'productUrl' => 'https://photos.google.com/lr/photo/page1',
                            'mediaMetadata' => ['creationTime' => '2026-01-01T00:00:00Z'],
                        ],
                    ],
                    'nextPageToken' => 'page-2',
                ])
                ->push([
                    'mediaItems' => [
                        [
                            'id' => 'media-page-2',
                            'filename' => 'page2.jpg',
                            'mimeType' => 'image/jpeg',
                            'productUrl' => 'https://photos.google.com/lr/photo/page2',
                            'mediaMetadata' => ['creationTime' => '2026-01-02T00:00:00Z'],
                        ],
                    ],
                ]),
        ]);

        $first = $this->postJson("/api/storage/google-photos/sync?account_id={$account->id}", [
            'max_batches' => 1,
        ]);
        $first->assertOk();
        $first->assertJsonPath('data.items_complete', false);
        $first->assertJsonPath('data.has_more', true);

        $second = $this->postJson("/api/storage/google-photos/sync?account_id={$account->id}", [
            'max_batches' => 1,
        ]);
        $second->assertOk();
        $second->assertJsonPath('data.items_complete', true);
        $second->assertJsonPath('data.has_more', false);

        $this->assertDatabaseHas('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => 'media-page-1',
        ]);

        $this->assertDatabaseHas('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => 'media-page-2',
        ]);
    }

    public function test_multi_page_sync_completes_in_single_call_with_multiple_batches(): void
    {
        $account = $this->googlePhotosAccount();

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response(['albums' => []]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::sequence()
                ->push([
                    'mediaItems' => [
                        [
                            'id' => 'media-a',
                            'filename' => 'a.jpg',
                            'mimeType' => 'image/jpeg',
                            'productUrl' => 'https://photos.google.com/lr/photo/a',
                            'mediaMetadata' => ['creationTime' => '2026-01-01T00:00:00Z'],
                        ],
                    ],
                    'nextPageToken' => 'page-2',
                ])
                ->push([
                    'mediaItems' => [
                        [
                            'id' => 'media-b',
                            'filename' => 'b.jpg',
                            'mimeType' => 'image/jpeg',
                            'productUrl' => 'https://photos.google.com/lr/photo/b',
                            'mediaMetadata' => ['creationTime' => '2026-01-02T00:00:00Z'],
                        ],
                    ],
                ]),
        ]);

        $response = $this->postJson("/api/storage/google-photos/sync?account_id={$account->id}", [
            'max_batches' => 2,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.items_synced', 2);
        $response->assertJsonPath('data.items_complete', true);
        $response->assertJsonPath('data.has_more', false);
    }

    public function test_provider_fetch_runs_outside_database_transaction(): void
    {
        $account = $this->googlePhotosAccount();
        $transactionLevelBeforeSync = DB::transactionLevel();
        $transactionLevelAtFetch = null;

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response(['albums' => []]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => function () use (&$transactionLevelAtFetch) {
                $transactionLevelAtFetch = DB::transactionLevel();

                return Http::response(['mediaItems' => []]);
            },
        ]);

        $response = $this->postJson("/api/storage/google-photos/sync?account_id={$account->id}", [
            'max_batches' => 1,
        ]);

        $response->assertOk();
        $this->assertSame($transactionLevelBeforeSync, $transactionLevelAtFetch);
    }

    public function test_background_sync_without_reconcile_keeps_stale_cache(): void
    {
        $account = $this->googlePhotosAccount();

        StorageRemoteItem::query()->create([
            'storage_account_id' => $account->id,
            'parent_remote_id' => '',
            'remote_id' => 'media-stale',
            'name' => 'stale.jpg',
            'mime_type' => 'image/jpeg',
            'modified_at' => now(),
            'synced_at' => now(),
        ]);

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response(['albums' => []]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response([
                'mediaItems' => [
                    [
                        'id' => 'media-new',
                        'filename' => 'new.jpg',
                        'mimeType' => 'image/jpeg',
                        'productUrl' => 'https://photos.google.com/lr/photo/new',
                        'mediaMetadata' => ['creationTime' => '2026-01-02T00:00:00Z'],
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson("/api/storage/google-photos/sync?account_id={$account->id}", [
            'max_batches' => 3,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => 'media-stale',
        ]);

        $this->assertDatabaseHas('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => 'media-new',
        ]);
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

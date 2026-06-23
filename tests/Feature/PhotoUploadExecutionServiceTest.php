<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PhotoTransferExecutionOutcome;
use App\Jobs\DownloadPhotoJob;
use App\Models\StorageAccount;
use App\Models\StorageUpload;
use App\Models\StoredFile;
use App\Models\TransferBatch;
use App\Models\TransferItem;
use App\Services\Flickr\PhotoUploadExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class PhotoUploadExecutionServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use RefreshDatabase;

    public function test_it_uploads_completed_local_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('flickr/friend@N01/photos/photo-1_abc123.jpg', 'jpeg-bytes');

        Http::fake([
            'photoslibrary.googleapis.com/v1/uploads' => Http::response('upload-token-123', 200),
            'photoslibrary.googleapis.com/v1/mediaItems:batchCreate' => Http::response([
                'newMediaItemResults' => [[
                    'mediaItem' => [
                        'id' => 'media-item-123',
                        'filename' => 'photo-1_original.jpg',
                    ],
                ]],
            ], 200),
        ]);

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        $storageAccount = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Photos',
            'credentials' => [
                'access_token' => 'token',
                'refresh_token' => 'refresh',
                'client_id' => 'client',
                'client_secret' => 'secret',
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
            'connected_at' => now(),
        ]);

        StoredFile::query()->create([
            'flickr_photo_id' => 'photo-1',
            'owner_nsid' => 'friend@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/friend@N01/photos/photo-1_abc123.jpg',
            'original_name' => 'photo-1_original.jpg',
        ]);

        $batch = TransferBatch::query()->create([
            'type' => 'upload',
            'connection_key' => $connection->connection_key,
            'storage_account_id' => $storageAccount->id,
            'subject_nsid' => 'friend@N01',
            'status' => 'running',
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => 'pending',
        ]);

        $outcome = app(PhotoUploadExecutionService::class)->execute(
            'photo-1',
            $storageAccount->id,
            $batch->id,
            'friend@N01',
            1,
            3,
        );

        $this->assertSame(PhotoTransferExecutionOutcome::Completed, $outcome);
        $this->assertSame('completed', StorageUpload::query()->first()->status);
        $this->assertSame('media-item-123', StorageUpload::query()->first()->remote_file_id);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame('completed', TransferItem::query()->first()->status);
    }

    public function test_it_short_circuits_when_already_uploaded(): void
    {
        Storage::fake('local');

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        $storageAccount = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Photos',
            'credentials' => [],
            'connected_at' => now(),
        ]);

        $storedFile = StoredFile::query()->create([
            'flickr_photo_id' => 'photo-1',
            'owner_nsid' => 'friend@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/friend@N01/photos/photo-1_abc123.jpg',
            'original_name' => 'photo-1_original.jpg',
        ]);

        StorageUpload::query()->create([
            'stored_file_id' => $storedFile->id,
            'storage_account_id' => $storageAccount->id,
            'status' => 'completed',
            'remote_file_id' => 'remote-1',
            'remote_path' => 'Flickr/friend@N01/Photos/photo-1_original.jpg',
        ]);

        $batch = TransferBatch::query()->create([
            'type' => 'upload',
            'connection_key' => $connection->connection_key,
            'storage_account_id' => $storageAccount->id,
            'subject_nsid' => 'friend@N01',
            'status' => 'running',
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => 'pending',
        ]);

        $outcome = app(PhotoUploadExecutionService::class)->execute(
            'photo-1',
            $storageAccount->id,
            $batch->id,
            'friend@N01',
            1,
            3,
        );

        $this->assertSame(PhotoTransferExecutionOutcome::Completed, $outcome);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame('completed', TransferItem::query()->first()->status);
    }

    public function test_it_defers_and_dispatches_download_when_local_file_missing(): void
    {
        Bus::fake([DownloadPhotoJob::class]);

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        $storageAccount = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Photos',
            'credentials' => [],
            'connected_at' => now(),
        ]);

        $batch = TransferBatch::query()->create([
            'type' => 'upload',
            'connection_key' => $connection->connection_key,
            'storage_account_id' => $storageAccount->id,
            'subject_nsid' => 'friend@N01',
            'status' => 'running',
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => 'pending',
        ]);

        $outcome = app(PhotoUploadExecutionService::class)->execute(
            'photo-1',
            $storageAccount->id,
            $batch->id,
            'friend@N01',
            1,
            3,
        );

        $this->assertSame(PhotoTransferExecutionOutcome::Deferred, $outcome);
        Bus::assertDispatched(DownloadPhotoJob::class, function (DownloadPhotoJob $job) use ($connection, $batch): bool {
            $reflection = new \ReflectionClass($job);

            $photoId = $reflection->getProperty('flickrPhotoId');
            $photoId->setAccessible(true);
            $ownerNsid = $reflection->getProperty('ownerNsid');
            $ownerNsid->setAccessible(true);
            $connectionKey = $reflection->getProperty('connectionKey');
            $connectionKey->setAccessible(true);
            $batchId = $reflection->getProperty('batchId');
            $batchId->setAccessible(true);

            return $photoId->getValue($job) === 'photo-1'
                && $ownerNsid->getValue($job) === 'friend@N01'
                && $connectionKey->getValue($job) === $connection->connection_key
                && $batchId->getValue($job) === $batch->id;
        });
    }
}

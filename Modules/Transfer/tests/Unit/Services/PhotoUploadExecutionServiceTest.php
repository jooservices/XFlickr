<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageUpload;
use Modules\Transfer\Enums\PhotoTransferExecutionOutcome;
use Modules\Transfer\Jobs\DownloadPhotoJob;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Services\PhotoUploadExecutionService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class PhotoUploadExecutionServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_it_uploads_completed_local_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('flickr/friend@N01/photos/photo-1_abc123.png', 'png-bytes');

        Http::fake([
            'photoslibrary.googleapis.com/v1/uploads' => Http::response('upload-token-123', 200),
            'photoslibrary.googleapis.com/v1/mediaItems:batchCreate' => Http::response([
                'newMediaItemResults' => [[
                    'mediaItem' => [
                        'id' => 'media-item-123',
                        'filename' => 'photo-1_original.png',
                    ],
                ]],
            ], 200),
        ]);

        $connection = $this->createFlickrConnection();

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
            'local_path' => 'flickr/friend@N01/photos/photo-1_abc123.png',
            'original_name' => 'photo-1_original.png',
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
        );

        $this->assertSame(PhotoTransferExecutionOutcome::Completed, $outcome);
        $this->assertSame('completed', StorageUpload::query()->first()->status);
        $this->assertSame('media-item-123', StorageUpload::query()->first()->remote_file_id);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame('completed', TransferItem::query()->first()->status);

        Http::assertSent(function ($request): bool {
            if (! str_contains((string) $request->url(), 'mediaItems:batchCreate')) {
                return false;
            }

            $data = $request->data();

            return ($data['newMediaItems'][0]['simpleMediaItem']['fileName'] ?? '') === 'photo-1_original.png';
        });
    }

    public function test_it_short_circuits_when_already_uploaded(): void
    {
        Storage::fake('local');

        $connection = $this->createFlickrConnection();

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
        );

        $this->assertSame(PhotoTransferExecutionOutcome::Completed, $outcome);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame('completed', TransferItem::query()->first()->status);
    }

    public function test_it_defers_without_dispatching_download_when_local_file_missing(): void
    {
        Bus::fake([DownloadPhotoJob::class]);

        $connection = $this->createFlickrConnection();

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
        );

        $this->assertSame(PhotoTransferExecutionOutcome::Deferred, $outcome);
        Bus::assertNotDispatched(DownloadPhotoJob::class);
    }

    public function test_it_defers_when_local_file_missing_even_after_many_readiness_checks(): void
    {
        Bus::fake([DownloadPhotoJob::class]);

        $connection = $this->createFlickrConnection();

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

        $service = app(PhotoUploadExecutionService::class);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $outcome = $service->execute(
                'photo-1',
                $storageAccount->id,
                $batch->id,
                'friend@N01',
            );

            $this->assertSame(PhotoTransferExecutionOutcome::Deferred, $outcome);
        }

        Bus::assertNotDispatched(DownloadPhotoJob::class);
        $this->assertSame('pending', TransferItem::query()->first()->status);
    }

    public function test_handle_failure_marks_upload_and_item_failed(): void
    {
        Storage::fake('local');

        $connection = $this->createFlickrConnection();

        $storageAccount = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Photos',
            'credentials' => [],
            'connected_at' => now(),
        ]);

        $storedFile = StoredFile::query()->create([
            'flickr_photo_id' => 'photo-fail',
            'owner_nsid' => FlickrNsid::fake(),
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/owner/photos/photo-fail.jpg',
            'original_name' => 'photo-fail_original.jpg',
        ]);

        $batch = TransferBatch::query()->create([
            'type' => 'upload',
            'connection_key' => $connection->connection_key,
            'storage_account_id' => $storageAccount->id,
            'subject_nsid' => $storedFile->owner_nsid,
            'status' => 'running',
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-fail',
            'status' => 'processing',
        ]);

        app(PhotoUploadExecutionService::class)->handleFailure(
            'photo-fail',
            $storageAccount->id,
            $batch->id,
            'provider rejected upload',
        );

        $this->assertSame('failed', TransferItem::query()->first()->status);
        $this->assertSame('provider rejected upload', TransferItem::query()->first()->error_message);
    }

    public function test_execute_throws_when_local_file_missing_after_marking_uploading(): void
    {
        Storage::fake('local');

        $connection = $this->createFlickrConnection();

        $storageAccount = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Photos',
            'credentials' => [],
            'connected_at' => now(),
        ]);

        $ownerNsid = FlickrNsid::fake();

        StoredFile::query()->create([
            'flickr_photo_id' => 'photo-missing',
            'owner_nsid' => $ownerNsid,
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/'.$ownerNsid.'/photos/missing.jpg',
            'original_name' => 'photo-missing_original.jpg',
        ]);

        $batch = TransferBatch::query()->create([
            'type' => 'upload',
            'connection_key' => $connection->connection_key,
            'storage_account_id' => $storageAccount->id,
            'subject_nsid' => $ownerNsid,
            'status' => 'running',
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-missing',
            'status' => 'pending',
        ]);

        try {
            app(PhotoUploadExecutionService::class)->execute(
                'photo-missing',
                $storageAccount->id,
                $batch->id,
                $ownerNsid,
            );
            $this->fail('Expected Exception');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('Local cached file missing', $exception->getMessage());
        }
    }
}

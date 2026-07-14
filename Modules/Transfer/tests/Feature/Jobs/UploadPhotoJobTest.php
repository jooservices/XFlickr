<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Feature\Jobs;

use DateTime;
use Illuminate\Support\Facades\Bus;
use Modules\Storage\Models\StorageAccount;
use Modules\Transfer\Jobs\UploadPhotoJob;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Services\PhotoUploadExecutionService;
use RuntimeException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class UploadPhotoJobTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_retry_until_scales_with_batch_size(): void
    {
        $smallBatch = new UploadPhotoJob('photo-1', 9, 42, 'owner@N01', 1);
        $largeBatch = new UploadPhotoJob('photo-2', 9, 42, 'owner@N01', 3000);

        $smallUntil = $smallBatch->retryUntil();
        $largeUntil = $largeBatch->retryUntil();

        $this->assertInstanceOf(DateTime::class, $smallUntil);
        $this->assertGreaterThan($smallUntil->getTimestamp(), $largeUntil->getTimestamp());
    }

    public function test_job_uses_upload_queue_and_overlap_middleware(): void
    {
        Bus::fake();

        $job = new UploadPhotoJob('photo-3', 5, null, '', 1);
        dispatch($job);

        Bus::assertDispatched(UploadPhotoJob::class, function (UploadPhotoJob $dispatched): bool {
            $middleware = $dispatched->middleware();

            return count($middleware) === 1;
        });
    }

    public function test_job_serializes_transfer_payload(): void
    {
        $job = new UploadPhotoJob('photo-4', 12, 88, 'owner@N01', 4);

        $this->assertSame('xflickr-uploads', $job->queue);
        $this->assertSame(3, $job->maxExceptions);
        $this->assertSame(45, $job->backoff);
    }

    public function test_handle_defers_when_local_file_is_not_ready(): void
    {
        $connection = $this->createFlickrConnection();
        $ownerNsid = FlickrNsid::fake();

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
            'subject_nsid' => $ownerNsid,
            'status' => 'running',
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-defer',
            'status' => 'pending',
        ]);

        $job = new UploadPhotoJob('photo-defer', $storageAccount->id, $batch->id, $ownerNsid, 1);
        $job->handle(app(PhotoUploadExecutionService::class));

        $this->assertSame('pending', TransferItem::query()->first()->status);
    }

    public function test_failed_marks_upload_and_item_as_failed(): void
    {
        $connection = $this->createFlickrConnection();
        $ownerNsid = FlickrNsid::fake();

        $storageAccount = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Photos',
            'credentials' => [],
            'connected_at' => now(),
        ]);

        StoredFile::query()->create([
            'flickr_photo_id' => 'photo-job-fail',
            'owner_nsid' => $ownerNsid,
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/'.$ownerNsid.'/photos/photo-job-fail.jpg',
            'original_name' => 'photo-job-fail_original.jpg',
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
            'flickr_photo_id' => 'photo-job-fail',
            'status' => 'processing',
        ]);

        $job = new UploadPhotoJob('photo-job-fail', $storageAccount->id, $batch->id, $ownerNsid, 1);
        $job->failed(new RuntimeException('upload exploded'));

        $this->assertSame('failed', TransferItem::query()->first()->status);
        $this->assertSame('upload exploded', TransferItem::query()->first()->error_message);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Modules\Crawler\Models\Photo;
use Modules\Storage\Models\StorageAccount;
use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Jobs\DownloadPhotoJob;
use Modules\Transfer\Jobs\UploadPhotoJob;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Services\TransferItemRetryService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class TransferItemRetryServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_retry_queues_failed_upload_item(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();
        $ownerNsid = FlickrNsid::fake();

        $storageAccount = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Photos',
            'credentials' => ['access_token' => 'token'],
            'connected_at' => now(),
        ]);

        $batch = TransferBatch::query()->create([
            'type' => 'upload',
            'connection_key' => $connection->connection_key,
            'storage_account_id' => $storageAccount->id,
            'subject_nsid' => $ownerNsid,
            'status' => TransferBatchStatus::CompletedWithErrors->value,
            'total_count' => 2,
        ]);

        $photoId = (string) fake()->numerify('#########');
        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => $photoId,
            'status' => TransferItemStatus::Failed->value,
            'error_message' => 'upload failed',
        ]);

        app(TransferItemRetryService::class)->retry($connection, $batch, $photoId);

        $this->assertSame(TransferItemStatus::Pending->value, TransferItem::query()->first()->status);
        Queue::assertPushed(UploadPhotoJob::class);
    }

    public function test_retry_uses_photo_owner_nsid_when_catalog_photo_exists(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();
        $catalogOwner = FlickrNsid::fake();

        Photo::query()->create([
            'flickr_photo_id' => 'catalog-photo',
            'owner_nsid' => $catalogOwner,
            'title' => fake()->sentence(2),
            'secret' => fake()->bothify('??????????'),
            'server' => (string) fake()->numberBetween(1, 9999),
            'raw_payload' => [],
        ]);

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => FlickrNsid::fake(),
            'status' => TransferBatchStatus::CompletedWithErrors->value,
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'catalog-photo',
            'status' => TransferItemStatus::Failed->value,
        ]);

        app(TransferItemRetryService::class)->retry($connection, $batch, 'catalog-photo');

        Queue::assertPushed(DownloadPhotoJob::class);
    }

    public function test_retry_rejects_upload_batch_without_storage_account(): void
    {
        $connection = $this->createFlickrConnection();

        $batch = TransferBatch::query()->create([
            'type' => 'upload',
            'connection_key' => $connection->connection_key,
            'storage_account_id' => null,
            'status' => TransferBatchStatus::CompletedWithErrors->value,
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => TransferItemStatus::Failed->value,
        ]);

        $this->expectException(ValidationException::class);

        app(TransferItemRetryService::class)->retry($connection, $batch, 'photo-1');
    }

    public function test_retry_aborts_when_batch_belongs_to_other_connection(): void
    {
        $connection = $this->createFlickrConnection();
        $other = $this->createFlickrConnection();

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $other->connection_key,
            'status' => TransferBatchStatus::CompletedWithErrors->value,
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => TransferItemStatus::Failed->value,
        ]);

        $this->expectException(HttpException::class);

        app(TransferItemRetryService::class)->retry($connection, $batch, 'photo-1');
    }
}

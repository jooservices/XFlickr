<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Feature\Controllers\Api\V1;

use Illuminate\Support\Facades\Queue;
use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Jobs\DownloadPhotoJob;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class TransferItemRetryTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_retry_queues_failed_download_item(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'contact@N02',
            'status' => TransferBatchStatus::CompletedWithErrors->value,
            'total_count' => 1,
            'completed_count' => 0,
            'failed_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => '12345',
            'status' => TransferItemStatus::Failed->value,
            'error_message' => 'timeout',
        ]);

        $response = $this->postJson(
            "/api/v1/flickr/accounts/{$connection->public_id}/transfers/{$batch->id}/items/12345/retries",
        );

        $response->assertAccepted()
            ->assertJsonPath('data.status', 'queued');

        $this->assertDatabaseHas('transfer_items', [
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => '12345',
            'status' => TransferItemStatus::Pending->value,
        ]);

        Queue::assertPushed(DownloadPhotoJob::class);
    }

    public function test_retry_rejects_non_failed_item(): void
    {
        $connection = $this->createFlickrConnection();

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'status' => TransferBatchStatus::Running->value,
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => '999',
            'status' => TransferItemStatus::Completed->value,
        ]);

        $response = $this->postJson(
            "/api/v1/flickr/accounts/{$connection->public_id}/transfers/{$batch->id}/items/999/retries",
        );

        $response->assertUnprocessable();
    }
}

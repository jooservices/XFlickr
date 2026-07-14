<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Services\TransferBatchReconciler;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class TransferBatchReconcilerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_it_marks_batch_completed_with_errors_when_some_items_failed(): void
    {
        $connection = $this->createFlickrConnection();

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'friend@N01',
            'status' => TransferBatchStatus::Running->value,
            'total_count' => 3,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => 'completed',
        ]);
        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-2',
            'status' => 'completed',
        ]);
        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-3',
            'status' => 'failed',
            'error_message' => 'HTTP download failed with status: 403',
        ]);

        app(TransferBatchReconciler::class)->reconcile($batch);

        $batch->refresh();
        $this->assertSame(TransferBatchStatus::CompletedWithErrors->value, $batch->status);
        $this->assertSame(2, $batch->completed_count);
        $this->assertSame(1, $batch->failed_count);
    }

    public function test_it_marks_batch_failed_when_all_items_failed(): void
    {
        $connection = $this->createFlickrConnection();

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'friend@N01',
            'status' => TransferBatchStatus::Running->value,
            'total_count' => 2,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => 'failed',
            'error_message' => 'HTTP download failed with status: 403',
        ]);
        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-2',
            'status' => 'failed',
            'error_message' => 'HTTP download failed with status: 403',
        ]);

        app(TransferBatchReconciler::class)->reconcile($batch);

        $batch->refresh();
        $this->assertSame(TransferBatchStatus::Failed->value, $batch->status);
        $this->assertSame(0, $batch->completed_count);
        $this->assertSame(2, $batch->failed_count);
    }

    public function test_it_reconciles_stale_counts_atomically(): void
    {
        $connection = $this->createFlickrConnection();

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'friend@N01',
            'status' => TransferBatchStatus::Running->value,
            'total_count' => 2,
            'completed_count' => 6,
            'failed_count' => 6,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => 'failed',
            'error_message' => 'HTTP download failed with status: 403',
        ]);
        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-2',
            'status' => 'failed',
            'error_message' => 'HTTP download failed with status: 403',
        ]);

        app(TransferBatchReconciler::class)->reconcile($batch);

        $batch->refresh();
        $this->assertSame(2, $batch->failed_count);
        $this->assertSame(0, $batch->completed_count);
        $this->assertSame(TransferBatchStatus::Failed->value, $batch->status);
    }
}

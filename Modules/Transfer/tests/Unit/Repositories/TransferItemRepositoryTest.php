<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Repositories;

use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Repositories\TransferItemRepository;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class TransferItemRepositoryTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    private TransferItemRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(TransferItemRepository::class);
    }

    public function test_create_pending_and_find_for_batch(): void
    {
        $batch = TransferBatch::factory()->create();

        $item = $this->repository->createPending($batch->id, 'photo-123');

        $this->assertSame(TransferItemStatus::Pending->value, $item->status);
        $this->assertSame($item->id, $this->repository->findForBatch($batch->id, 'photo-123')?->id);
    }

    public function test_create_pending_bulk_is_noop_for_empty_list(): void
    {
        $batch = TransferBatch::factory()->create();

        $this->repository->createPendingBulk($batch->id, []);

        $this->assertSame(0, TransferItem::query()->where('transfer_batch_id', $batch->id)->count());
    }

    public function test_update_status_and_counts(): void
    {
        $batch = TransferBatch::factory()->create();
        $this->repository->createPending($batch->id, 'photo-a');
        $this->repository->createPending($batch->id, 'photo-b');

        $this->repository->markCompleted($batch->id, 'photo-a');
        $this->repository->updateStatus($batch->id, 'photo-b', TransferItemStatus::Failed, 'boom');

        $this->assertSame(1, $this->repository->countByStatus($batch->id, TransferItemStatus::Completed));
        $this->assertSame(1, $this->repository->countByStatus($batch->id, TransferItemStatus::Failed));
        $this->assertSame('boom', $this->repository->latestErrorMessage($batch->id));
    }

    public function test_failed_counts_grouped_by_connection_since(): void
    {
        $connection = $this->createFlickrConnection();
        $since = now()->subMinute();

        $batch = TransferBatch::factory()->create([
            'connection_key' => $connection->connection_key,
            'type' => 'upload',
        ]);
        TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'failed-photo',
            'status' => TransferItemStatus::Failed->value,
        ]);

        $this->assertSame(1, $this->repository->countFailedSince($since));
        $this->assertSame(1, $this->repository->countFailedForConnectionSince($connection->connection_key, $since));
        $this->assertSame(
            [$connection->connection_key => 1],
            $this->repository->countFailedGroupedByConnectionSince([$connection->connection_key], $since),
        );
        $this->assertSame([], $this->repository->countFailedGroupedByConnectionSince([], $since));
    }

    public function test_list_failed_for_batch_limits_rows(): void
    {
        $batch = TransferBatch::factory()->create();

        foreach (range(1, 3) as $index) {
            TransferItem::factory()->create([
                'transfer_batch_id' => $batch->id,
                'flickr_photo_id' => 'failed-'.$index,
                'status' => TransferItemStatus::Failed->value,
                'error_message' => 'error-'.$index,
            ]);
        }

        $failed = $this->repository->listFailedForBatch($batch->id, 2);

        $this->assertCount(2, $failed);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Repositories;

use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Repositories\TransferItemRepository;
use Modules\Transfer\Tests\TestCase;

final class TransferItemRepositoryTest extends TestCase
{
    public function test_create_update_find_and_list_failed_items(): void
    {
        $batch = TransferBatch::factory()->create(['connection_key' => 'connection-a']);
        $repository = app(TransferItemRepository::class);

        $repository->createPendingBulk($batch->id, []);
        $first = $repository->createPending($batch->id, 'photo-1');
        $repository->createPendingBulk($batch->id, ['photo-2', 'photo-3']);

        $this->assertSame(TransferItemStatus::Pending->value, $first->status);
        $this->assertSame(3, $repository->countByStatus($batch->id, TransferItemStatus::Pending));

        $repository->markCompleted($batch->id, 'photo-1');
        $repository->updateStatus($batch->id, 'photo-2', TransferItemStatus::Failed, 'first error');
        $repository->updateStatus($batch->id, 'photo-3', TransferItemStatus::Failed, 'latest error');

        $this->assertSame(1, $repository->countByStatus($batch->id, TransferItemStatus::Completed));
        $this->assertSame(2, $repository->countFailedSince(now()->subMinute()));
        $this->assertSame(2, $repository->countFailedForConnectionSince('connection-a', now()->subMinute()));
        $this->assertSame(['connection-a' => 2], $repository->countFailedGroupedByConnectionSince(['connection-a'], now()->subMinute()));
        $this->assertSame([], $repository->countFailedGroupedByConnectionSince([], now()->subMinute()));
        $this->assertSame('latest error', $repository->latestErrorMessage($batch->id));
        $this->assertSame('photo-1', $repository->findForBatch($batch->id, 'photo-1')?->source_id);
        $this->assertNull($repository->findForBatch($batch->id, 'missing'));
        $this->assertSame(['photo-3'], $repository->listFailedForBatch($batch->id, 1)->pluck('source_id')->all());
    }
}

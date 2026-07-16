<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Illuminate\Support\Facades\Event;
use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Events\TransferBatchReconciled;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Services\TransferBatchReconciler;
use Modules\Transfer\Tests\TestCase;

final class TransferBatchReconcilerTest extends TestCase
{
    public function test_reconcile_updates_counts_and_dispatches_the_committed_state(): void
    {
        Event::fake([TransferBatchReconciled::class]);
        $batch = TransferBatch::factory()->create([
            'status' => TransferBatchStatus::Running->value,
            'total_count' => 2,
        ]);
        TransferItem::factory()->for($batch, 'batch')->create([
            'status' => TransferItemStatus::Completed->value,
        ]);
        TransferItem::factory()->for($batch, 'batch')->create([
            'status' => TransferItemStatus::Failed->value,
            'error_message' => 'Provider rejected the item.',
        ]);

        app(TransferBatchReconciler::class)->reconcile($batch->id);

        $batch->refresh();
        $this->assertSame(1, $batch->completed_count);
        $this->assertSame(1, $batch->failed_count);
        $this->assertSame(TransferBatchStatus::CompletedWithErrors->value, $batch->status);
        Event::assertDispatched(
            TransferBatchReconciled::class,
            fn (TransferBatchReconciled $event): bool => $event->batchId === $batch->id
                && $event->status === TransferBatchStatus::CompletedWithErrors->value
                && $event->completedCount === 1
                && $event->failedCount === 1
                && $event->sampleError === 'Provider rejected the item.',
        );
    }

    public function test_reconcile_ignores_a_missing_batch(): void
    {
        Event::fake([TransferBatchReconciled::class]);

        app(TransferBatchReconciler::class)->reconcile(PHP_INT_MAX);

        Event::assertNotDispatched(TransferBatchReconciled::class);
    }
}

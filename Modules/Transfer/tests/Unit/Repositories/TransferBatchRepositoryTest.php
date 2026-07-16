<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Repositories;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Storage\Models\StorageAccount;
use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Repositories\TransferBatchRepository;
use Modules\Transfer\Tests\TestCase;

final class TransferBatchRepositoryTest extends TestCase
{
    public function test_download_batch_and_items_are_created_atomically(): void
    {
        $batch = app(TransferBatchRepository::class)->createDownloadBatchWithItems(
            'owner@N01',
            'subject@N01',
            ['group_type' => 'bulk', 'group_id' => null, 'group_label' => 'Selected photos'],
            ['photo-a', 'photo-b'],
        );

        $this->assertSame(2, $batch->total_count);
        $this->assertSame(2, TransferItem::query()->where('transfer_batch_id', $batch->id)->count());
    }

    public function test_item_insert_failure_rolls_back_the_batch(): void
    {
        DB::statement(<<<'SQL'
            CREATE TRIGGER fail_transfer_item_insert
            BEFORE INSERT ON transfer_items
            BEGIN
                SELECT RAISE(ABORT, 'forced transfer item failure');
            END
            SQL);

        try {
            app(TransferBatchRepository::class)->createDownloadBatchWithItems(
                'owner@N01',
                'subject@N01',
                ['group_type' => 'bulk', 'group_id' => null, 'group_label' => 'Selected photos'],
                ['photo-a'],
            );

            $this->fail('Expected transfer item persistence to fail.');
        } catch (QueryException) {
            $this->assertSame(0, TransferBatch::query()->count());
            $this->assertSame(0, TransferItem::query()->count());
        }
    }

    public function test_upload_batch_queries_and_counts(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $repository = app(TransferBatchRepository::class);
        $batch = $repository->createUploadBatchWithItems(
            'connection-a',
            $account->id,
            ['photo-a', 'photo-b'],
            'owner@N01',
            true,
        );

        $this->assertTrue($batch->is($repository->findById($batch->id)));
        $this->assertNull($repository->findById(999999));
        $this->assertSame('connection-a', $repository->connectionKeyForId($batch->id));
        $this->assertNull($repository->connectionKeyForId(999999));
        $this->assertSame(1, $repository->countByTypeAndStatus('upload', TransferBatchStatus::Running->value));
        $this->assertSame(1, $repository->countActiveForConnection('connection-a', 'upload'));
        $this->assertSame([], $repository->countActiveGroupedByConnection([], 'upload'));
        $this->assertSame(['connection-a' => 1], $repository->countActiveGroupedByConnection(['connection-a'], 'upload'));
        $this->assertSame([], $repository->runningDownloadsForSubjects('connection-a', [])->all());
        $this->assertSame(1, $repository->queryForConnection('connection-a')->count());
        $this->assertCount(2, $repository->findWithItemsForConnection($batch->id, 'connection-a')?->items ?? []);
        $this->assertNull($repository->findWithItemsForConnection($batch->id, 'other'));
        $this->assertCount(1, $repository->listForConnection(
            'connection-a',
            TransferBatchStatus::Running->value,
            'upload',
            true,
            'id',
            'desc',
            10,
            ['id'],
        ));
    }

    public function test_reconcile_handles_missing_running_failed_and_completed_batches(): void
    {
        $repository = app(TransferBatchRepository::class);
        $this->assertNull($repository->reconcile(999999));

        $batch = TransferBatch::factory()->create([
            'connection_key' => 'connection-a',
            'status' => TransferBatchStatus::Failed->value,
            'total_count' => 3,
        ]);
        TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'status' => TransferItemStatus::Completed->value,
        ]);
        $running = $repository->reconcile($batch->id);
        $this->assertSame(TransferBatchStatus::Running->value, $running['status']);

        TransferItem::factory()->count(2)->create([
            'transfer_batch_id' => $batch->id,
            'status' => TransferItemStatus::Failed->value,
            'error_message' => 'failed',
        ]);
        $partial = $repository->reconcile($batch->id);
        $this->assertSame(TransferBatchStatus::CompletedWithErrors->value, $partial['status']);

        $failed = TransferBatch::factory()->create(['total_count' => 1]);
        TransferItem::factory()->create([
            'transfer_batch_id' => $failed->id,
            'status' => TransferItemStatus::Failed->value,
        ]);
        $this->assertSame(TransferBatchStatus::Failed->value, $repository->reconcile($failed->id)['status']);

        $completed = TransferBatch::factory()->create(['total_count' => 1]);
        TransferItem::factory()->create([
            'transfer_batch_id' => $completed->id,
            'status' => TransferItemStatus::Completed->value,
        ]);
        $this->assertSame(TransferBatchStatus::Completed->value, $repository->reconcile($completed->id)['status']);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use JOOservices\LaravelLogging\Jobs\StoreActivityLogJob;
use Modules\Storage\Models\StorageAccount;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Jobs\DownloadFileJob;
use Modules\Transfer\Jobs\UploadFileJob;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Services\TransferBatchService;
use Modules\Transfer\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TransferBatchServiceTest extends TestCase
{
    public function test_queue_downloads_creates_pending_files_batch_items_and_jobs(): void
    {
        Queue::fake();

        $result = app(TransferBatchService::class)->queueDownloads([
            ['source_type' => 'flickr_photo', 'source_id' => 'photo-a', 'source_owner' => 'owner@N01'],
            ['source_type' => 'flickr_photo', 'source_id' => 'photo-b', 'source_owner' => 'owner@N01'],
        ], 'connection-a', 'owner@N01', 'bulk', null, 'Selected photos');

        $this->assertSame('success', $result->flashKey);
        $this->assertSame(2, $result->queuedCount);
        $this->assertDatabaseCount('stored_files', 2);
        $this->assertDatabaseCount('transfer_batches', 1);
        $this->assertDatabaseCount('transfer_items', 2);
        Queue::assertPushed(DownloadFileJob::class, 2);
    }

    public function test_queue_methods_reject_empty_or_missing_files(): void
    {
        $service = app(TransferBatchService::class);

        $this->assertSame('error', $service->queueDownloads([], 'connection-a')->flashKey);
        $this->assertSame('error', $service->queueUploads([], 1, 'connection-a')->flashKey);
        $this->assertSame('error', $service->queueUploads([999999], 1, 'connection-a')->flashKey);
    }

    public function test_queue_uploads_ignores_missing_ids_and_dispatches_valid_files(): void
    {
        Queue::fake();
        $account = StorageAccount::factory()->googleDrive()->create();
        $file = StoredFile::factory()->create([
            'source_id' => 'photo-upload',
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
        ]);

        $result = app(TransferBatchService::class)->queueUploads(
            [$file->id, 999999],
            $account->id,
            'connection-a',
            'owner@N01',
            true,
        );

        $this->assertSame('success', $result->flashKey);
        $this->assertSame(1, $result->queuedCount);
        $this->assertDatabaseHas('transfer_batches', [
            'type' => 'upload',
            'storage_account_id' => $account->id,
            'delete_local_after_upload' => true,
        ]);
        Queue::assertPushed(UploadFileJob::class, 1);
    }

    public function test_query_methods_return_repository_results(): void
    {
        $failedBatch = TransferBatch::factory()->create([
            'connection_key' => 'connection-a',
            'subject_nsid' => 'owner@N01',
            'type' => 'download',
            'status' => TransferBatchStatus::Running->value,
            'total_count' => 2,
            'failed_count' => 1,
        ]);
        TransferItem::factory()->create([
            'transfer_batch_id' => $failedBatch->id,
            'source_id' => 'photo-failed',
            'status' => TransferItemStatus::Failed->value,
            'error_message' => 'download failed',
        ]);
        $file = StoredFile::factory()->create([
            'source_id' => 'photo-completed',
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
        ]);

        $service = app(TransferBatchService::class);

        $this->assertTrue($file->is($service->findStoredFile('flickr_photo', 'photo-completed')));
        $this->assertSame(['photo-completed'], $service->completedSourceIds('flickr_photo', ['photo-completed']));
        $this->assertSame(1, $service->countStoredFiles());
        $this->assertSame(1, $service->countBatchesByTypeAndStatus('download', TransferBatchStatus::Running->value));
        $this->assertSame(['connection-a' => 1], $service->countActiveBatchesGroupedByConnection(['connection-a'], 'download'));
        $this->assertSame(1, $service->countFailedItemsSince(now()->subMinute()));
        $this->assertSame(['connection-a' => 1], $service->countFailedItemsGroupedByConnectionSince(['connection-a'], now()->subMinute()));
        $this->assertCount(1, $service->runningDownloadsForSubjects('connection-a', ['owner@N01']));

        $detail = $service->batchDetail('connection-a', $failedBatch);
        $this->assertSame($failedBatch->id, $detail['batch']['id']);
        $this->assertCount(1, $detail['items']);
        $this->assertNull($service->batchDetail('other-connection', $failedBatch));

        $list = $service->batchesForConnection('connection-a', null, null, true, 'id', 'desc', 25);
        $this->assertSame('download failed', $list['data']->first()['sample_error']);
    }

    public function test_retry_download_resets_item_and_dispatches_job(): void
    {
        Queue::fake();
        Event::fake();
        $batch = TransferBatch::factory()->create([
            'connection_key' => 'connection-a',
            'subject_nsid' => 'subject@N01',
            'type' => 'download',
            'status' => TransferBatchStatus::Failed->value,
            'total_count' => 1,
            'failed_count' => 1,
        ]);
        $item = TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => 'photo-retry',
            'status' => TransferItemStatus::Failed->value,
        ]);
        StoredFile::factory()->create([
            'source_type' => 'custom-source',
            'source_id' => 'photo-retry',
            'source_owner' => 'stored-owner@N01',
            'variant' => 'original',
        ]);

        app(TransferBatchService::class)->retryItem('connection-a', $batch, 'photo-retry');

        $this->assertSame(TransferItemStatus::Pending->value, $item->refresh()->status);
        Queue::assertPushed(DownloadFileJob::class, 1);
        Queue::assertPushedOn('logging', StoreActivityLogJob::class);
    }

    public function test_retry_rejects_wrong_connection_and_nonfailed_item(): void
    {
        $batch = TransferBatch::factory()->create(['connection_key' => 'connection-a']);
        TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => 'photo-pending',
            'status' => TransferItemStatus::Pending->value,
        ]);
        $service = app(TransferBatchService::class);

        try {
            $service->retryItem('other-connection', $batch, 'photo-pending');
            $this->fail('Expected a not-found response.');
        } catch (NotFoundHttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $this->expectException(ValidationException::class);
        $service->retryItem('connection-a', $batch, 'photo-pending');
    }

    public function test_retry_upload_validates_account_and_stored_file_then_dispatches(): void
    {
        Queue::fake();
        Event::fake();
        $account = StorageAccount::factory()->googleDrive()->create();
        $batch = TransferBatch::factory()->create([
            'connection_key' => 'connection-a',
            'type' => 'upload',
            'storage_account_id' => $account->id,
            'status' => TransferBatchStatus::Failed->value,
            'total_count' => 1,
            'failed_count' => 1,
        ]);
        TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => 'photo-upload-retry',
            'status' => TransferItemStatus::Failed->value,
        ]);
        $stored = StoredFile::factory()->create([
            'source_id' => 'photo-upload-retry',
            'variant' => 'original',
        ]);

        app(TransferBatchService::class)->retryItem('connection-a', $batch, 'photo-upload-retry');

        Queue::assertPushed(UploadFileJob::class, 1);
        $this->assertNotNull($stored->id);
    }

    public function test_retry_upload_rejects_missing_account_or_file(): void
    {
        $service = app(TransferBatchService::class);
        $batchWithoutAccount = TransferBatch::factory()->create([
            'connection_key' => 'connection-a',
            'type' => 'upload',
            'status' => TransferBatchStatus::Failed->value,
        ]);
        TransferItem::factory()->create([
            'transfer_batch_id' => $batchWithoutAccount->id,
            'source_id' => 'missing-account',
            'status' => TransferItemStatus::Failed->value,
        ]);

        try {
            $service->retryItem('connection-a', $batchWithoutAccount, 'missing-account');
            $this->fail('Expected missing storage account validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('batch', $exception->errors());
        }

        $account = StorageAccount::factory()->googleDrive()->create();
        $batchWithoutFile = TransferBatch::factory()->create([
            'connection_key' => 'connection-a',
            'type' => 'upload',
            'storage_account_id' => $account->id,
            'status' => TransferBatchStatus::Failed->value,
        ]);
        TransferItem::factory()->create([
            'transfer_batch_id' => $batchWithoutFile->id,
            'source_id' => 'missing-file',
            'status' => TransferItemStatus::Failed->value,
        ]);

        $this->expectException(ValidationException::class);
        $service->retryItem('connection-a', $batchWithoutFile, 'missing-file');
    }

    public function test_jobs_are_not_dispatched_when_batch_creation_rolls_back(): void
    {
        Queue::fake();
        DB::statement(<<<'SQL'
            CREATE TRIGGER fail_transfer_item_insert
            BEFORE INSERT ON transfer_items
            BEGIN
                SELECT RAISE(ABORT, 'forced transfer item failure');
            END
            SQL);

        try {
            app(TransferBatchService::class)->queueDownloads([
                ['source_type' => 'photo', 'source_id' => 'photo-a', 'source_owner' => 'owner@N01'],
            ], 'owner@N01');

            $this->fail('Expected transfer item persistence to fail.');
        } catch (QueryException) {
            Queue::assertNothingPushed();
            $this->assertSame(0, TransferBatch::query()->count());
            $this->assertSame(0, TransferItem::query()->count());
        }
    }
}

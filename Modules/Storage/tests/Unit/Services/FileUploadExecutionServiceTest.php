<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Modules\Storage\Enums\StoredFileStatus;
use Modules\Storage\Enums\TransferExecutionOutcome;
use Modules\Storage\Enums\TransferItemStatus;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StoredFile;
use Modules\Storage\Models\TransferBatch;
use Modules\Storage\Models\TransferItem;
use Modules\Storage\Services\FileUploadExecutionService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class FileUploadExecutionServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_execute_returns_deferred_when_stored_file_not_found(): void
    {
        $service = app(FileUploadExecutionService::class);
        $result = $service->execute(999999, 1, null);

        $this->assertSame(TransferExecutionOutcome::Deferred, $result);
    }

    public function test_execute_returns_deferred_when_stored_file_not_completed(): void
    {
        $storedFile = StoredFile::factory()->create([
            'status' => StoredFileStatus::Pending->value,
        ]);

        $service = app(FileUploadExecutionService::class);
        $result = $service->execute($storedFile->id, 1, null);

        $this->assertSame(TransferExecutionOutcome::Deferred, $result);
    }

    public function test_handle_failure_marks_upload_failed_and_reconciles(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();

        $storedFile = StoredFile::factory()->create([
            'status' => StoredFileStatus::Completed->value,
        ]);

        $batch = TransferBatch::factory()->create(['total_count' => 1]);

        TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => $storedFile->source_id,
        ]);

        $service = app(FileUploadExecutionService::class);
        $service->handleFailure($storedFile->id, $account->id, $batch->id, 'Upload timeout');

        $item = TransferItem::query()
            ->where('transfer_batch_id', $batch->id)
            ->where('source_id', $storedFile->source_id)
            ->first();

        $this->assertSame(TransferItemStatus::Failed->value, $item->status);
    }
}

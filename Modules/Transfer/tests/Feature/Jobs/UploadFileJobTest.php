<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Feature\Jobs;

use Illuminate\Queue\Middleware\WithoutOverlapping;
use Modules\Storage\Models\StorageAccount;
use Modules\Transfer\Enums\StorageUploadStatus;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Jobs\UploadFileJob;
use Modules\Transfer\Models\StorageUpload;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Services\FileUploadExecutionService;
use Modules\Transfer\Tests\TestCase;
use RuntimeException;

final class UploadFileJobTest extends TestCase
{
    public function test_job_dispatches_on_correct_queue(): void
    {
        $job = new UploadFileJob(
            storedFileId: fake()->numberBetween(1, 100),
            storageAccountId: fake()->numberBetween(1, 10),
        );

        $this->assertSame('xflickr-uploads', $job->queue);
    }

    public function test_job_has_without_overlapping_middleware(): void
    {
        $job = new UploadFileJob(
            storedFileId: fake()->numberBetween(1, 100),
            storageAccountId: fake()->numberBetween(1, 10),
        );

        $middleware = $job->middleware();

        $this->assertNotEmpty($middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_max_exceptions_is_three(): void
    {
        $job = new UploadFileJob(
            storedFileId: fake()->numberBetween(1, 100),
            storageAccountId: fake()->numberBetween(1, 10),
        );

        $this->assertSame(3, $job->maxExceptions);
    }

    public function test_retry_window_scales_with_batch_size(): void
    {
        $job = new UploadFileJob(1, 1, null, 3000);

        $this->assertGreaterThan(now()->addHours(8)->timestamp, $job->retryUntil()->getTimestamp());
    }

    public function test_handle_completes_item_when_upload_already_exists(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $file = StoredFile::factory()->create([
            'source_id' => 'photo-uploaded',
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
        ]);
        StorageUpload::factory()->create([
            'stored_file_id' => $file->id,
            'storage_account_id' => $account->id,
            'status' => StorageUploadStatus::Completed->value,
        ]);
        $batch = TransferBatch::factory()->create(['total_count' => 1]);
        $item = TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => $file->source_id,
        ]);
        $job = new UploadFileJob($file->id, $account->id, $batch->id);

        $job->handle(app(FileUploadExecutionService::class));

        $this->assertSame(TransferItemStatus::Completed->value, $item->refresh()->status);
    }

    public function test_failed_marks_upload_and_item_failed(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $file = StoredFile::factory()->create([
            'source_id' => 'photo-upload-failed',
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
        ]);
        $batch = TransferBatch::factory()->create(['total_count' => 1]);
        $item = TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => $file->source_id,
        ]);
        StorageUpload::factory()->create([
            'stored_file_id' => $file->id,
            'storage_account_id' => $account->id,
            'status' => StorageUploadStatus::Pending->value,
        ]);
        $job = new UploadFileJob($file->id, $account->id, $batch->id);

        $job->failed(new RuntimeException('terminal failure'));

        $this->assertDatabaseHas('storage_uploads', [
            'stored_file_id' => $file->id,
            'storage_account_id' => $account->id,
            'status' => StorageUploadStatus::Failed->value,
        ]);
        $this->assertSame(TransferItemStatus::Failed->value, $item->refresh()->status);
    }
}

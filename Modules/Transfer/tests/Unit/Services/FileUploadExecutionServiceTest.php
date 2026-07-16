<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Aws\S3\S3Client;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageFlysystemFactory;
use Modules\Transfer\Enums\StorageUploadStatus;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Enums\TransferExecutionOutcome;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Models\StorageUpload;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Services\FileUploadExecutionService;
use Modules\Transfer\Tests\TestCase;
use RuntimeException;
use Tests\Concerns\SafeRefreshDatabase;

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

    public function test_execute_marks_upload_pending_when_local_file_is_missing(): void
    {
        Storage::fake();
        $account = StorageAccount::factory()->r2()->create();
        $storedFile = StoredFile::factory()->create([
            'status' => StoredFileStatus::Completed->value,
            'variant' => 'original',
            'local_path' => 'downloads/missing.jpg',
        ]);
        $batch = TransferBatch::factory()->create(['total_count' => 1]);
        $item = TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => $storedFile->source_id,
        ]);

        try {
            app(FileUploadExecutionService::class)->execute($storedFile->id, $account->id, $batch->id);
            $this->fail('Expected the missing local file to fail.');
        } catch (Exception $exception) {
            $this->assertStringContainsString('missing', $exception->getMessage());
        }

        $this->assertDatabaseHas('storage_uploads', [
            'stored_file_id' => $storedFile->id,
            'storage_account_id' => $account->id,
            'status' => StorageUploadStatus::Pending->value,
        ]);
        $this->assertSame(TransferItemStatus::Processing->value, $item->refresh()->status);
    }

    public function test_execute_uploads_through_storage_adapter_and_deletes_local_when_requested(): void
    {
        Storage::fake();
        $localPath = 'downloads/ready.jpg';
        Storage::put($localPath, 'ready image');
        $account = StorageAccount::factory()->r2()->create();
        $storedFile = StoredFile::factory()->create([
            'source_id' => 'photo-ready',
            'source_owner' => 'owner@N01',
            'status' => StoredFileStatus::Completed->value,
            'variant' => 'original',
            'local_path' => $localPath,
        ]);
        $batch = TransferBatch::factory()->create([
            'type' => 'upload',
            'storage_account_id' => $account->id,
            'total_count' => 1,
            'delete_local_after_upload' => true,
        ]);
        $item = TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => $storedFile->source_id,
        ]);
        $this->bindInMemoryStorageDisk();

        $outcome = app(FileUploadExecutionService::class)->execute(
            $storedFile->id,
            $account->id,
            $batch->id,
        );

        $this->assertSame(TransferExecutionOutcome::Completed, $outcome);
        $this->assertDatabaseHas('storage_uploads', [
            'stored_file_id' => $storedFile->id,
            'storage_account_id' => $account->id,
            'status' => StorageUploadStatus::Completed->value,
        ]);
        $this->assertSame(TransferItemStatus::Completed->value, $item->refresh()->status);
        $this->assertNull($storedFile->refresh()->local_path);
        Storage::assertMissing($localPath);
    }

    public function test_execute_returns_completed_when_upload_is_already_completed(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $storedFile = StoredFile::factory()->create([
            'status' => StoredFileStatus::Completed->value,
            'variant' => 'original',
        ]);
        StorageUpload::factory()->create([
            'stored_file_id' => $storedFile->id,
            'storage_account_id' => $account->id,
            'status' => StorageUploadStatus::Completed->value,
        ]);

        $outcome = app(FileUploadExecutionService::class)->execute($storedFile->id, $account->id, null);

        $this->assertSame(TransferExecutionOutcome::Completed, $outcome);
    }

    private function bindInMemoryStorageDisk(): Filesystem
    {
        $adapter = new InMemoryFilesystemAdapter;
        $league = new LeagueFilesystem($adapter);
        $disk = new FilesystemAdapter($league, $adapter, ['driver' => 'memory']);
        $factory = \Mockery::mock(StorageFlysystemFactory::class);
        $factory->shouldReceive('diskForAccount')->andReturn($disk);
        $client = \Mockery::mock(S3Client::class);
        $client->shouldReceive('headObject')->andThrow(new RuntimeException('Metadata unavailable'));
        $factory->shouldReceive('r2Client')->andReturn($client);
        $this->app->instance(StorageFlysystemFactory::class, $factory);

        return $disk;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Repositories;

use Modules\Storage\Enums\StorageUploadStatus;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageUpload;
use Modules\Storage\Repositories\StorageUploadRepository;
use Modules\Transfer\Models\StoredFile;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageUploadRepositoryTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_completed_stored_file_ids_for_account_returns_empty_for_empty_input(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();

        $ids = app(StorageUploadRepository::class)->completedStoredFileIdsForAccount([], $account->id);

        $this->assertSame([], $ids);
    }

    public function test_completed_stored_file_ids_for_account_returns_only_completed_rows(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $completedFile = StoredFile::factory()->create();
        $pendingFile = StoredFile::factory()->create();

        StorageUpload::factory()->create([
            'storage_account_id' => $account->id,
            'stored_file_id' => $completedFile->id,
            'status' => StorageUploadStatus::Completed->value,
        ]);
        StorageUpload::factory()->create([
            'storage_account_id' => $account->id,
            'stored_file_id' => $pendingFile->id,
            'status' => StorageUploadStatus::Pending->value,
        ]);

        $ids = app(StorageUploadRepository::class)->completedStoredFileIdsForAccount(
            [$completedFile->id, $pendingFile->id],
            $account->id,
        );

        $this->assertSame([$completedFile->id], $ids);
    }

    public function test_has_completed_reports_upload_state(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $storedFile = StoredFile::factory()->create();

        StorageUpload::factory()->create([
            'storage_account_id' => $account->id,
            'stored_file_id' => $storedFile->id,
            'status' => StorageUploadStatus::Completed->value,
        ]);

        $repository = app(StorageUploadRepository::class);

        $this->assertTrue($repository->hasCompleted($storedFile->id, $account->id));
        $this->assertFalse($repository->hasCompleted($storedFile->id + 999, $account->id));
    }

    public function test_first_or_create_for_account_creates_pending_row(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $storedFile = StoredFile::factory()->create();

        $upload = app(StorageUploadRepository::class)->firstOrCreateForAccount($storedFile->id, $account->id);

        $this->assertSame(StorageUploadStatus::Pending->value, $upload->status);
        $this->assertSame($storedFile->id, $upload->stored_file_id);
        $this->assertSame($account->id, $upload->storage_account_id);
    }

    public function test_mark_methods_update_upload_status(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $storedFile = StoredFile::factory()->create();
        $remoteId = fake()->uuid();

        $upload = StorageUpload::factory()->create([
            'storage_account_id' => $account->id,
            'stored_file_id' => $storedFile->id,
            'status' => StorageUploadStatus::Pending->value,
        ]);

        $repository = app(StorageUploadRepository::class);
        $repository->markUploading($storedFile->id, $account->id);
        $this->assertSame(StorageUploadStatus::Uploading->value, $upload->refresh()->status);

        $repository->markFailedForAccount($storedFile->id, $account->id, 'upload failed');
        $upload->refresh();
        $this->assertSame(StorageUploadStatus::Failed->value, $upload->status);
        $this->assertSame('upload failed', $upload->error_message);

        $repository->markCompletedForAccount($storedFile->id, $account->id, [
            'id' => $remoteId,
            'path' => '/photos/'.$remoteId,
            'etag' => fake()->md5(),
        ]);
        $upload->refresh();
        $this->assertSame(StorageUploadStatus::Completed->value, $upload->status);
        $this->assertSame($remoteId, $upload->remote_file_id);
        $this->assertNotNull($upload->uploaded_at);

        $repository->markPendingForAccount($storedFile->id, $account->id, 'retry later');
        $upload->refresh();
        $this->assertSame(StorageUploadStatus::Pending->value, $upload->status);
        $this->assertSame('retry later', $upload->error_message);
    }

    public function test_delete_by_remote_references_noops_for_empty_ids(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();

        app(StorageUploadRepository::class)->deleteByRemoteReferences($account->id, []);

        $this->assertDatabaseCount('storage_uploads', 0);
    }

    public function test_delete_by_remote_references_removes_matching_rows(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $remoteId = fake()->uuid();
        $remotePath = '/photos/'.$remoteId;

        StorageUpload::factory()->create([
            'storage_account_id' => $account->id,
            'remote_file_id' => $remoteId,
            'remote_path' => $remotePath,
        ]);
        StorageUpload::factory()->create([
            'storage_account_id' => $account->id,
            'remote_file_id' => fake()->uuid(),
            'remote_path' => '/photos/other',
        ]);

        app(StorageUploadRepository::class)->deleteByRemoteReferences($account->id, [$remoteId, $remotePath]);

        $this->assertDatabaseMissing('storage_uploads', [
            'storage_account_id' => $account->id,
            'remote_file_id' => $remoteId,
        ]);
        $this->assertDatabaseCount('storage_uploads', 1);
    }
}

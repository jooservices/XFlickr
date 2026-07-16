<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Repositories;

use Illuminate\Support\Facades\Storage;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Repositories\StoredFileRepository;
use Modules\Transfer\Tests\TestCase;

final class StoredFileRepositoryTest extends TestCase
{
    public function test_lookup_methods_filter_originals_and_completed_rows(): void
    {
        $completed = StoredFile::factory()->create([
            'source_id' => 'completed-1',
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
        ]);
        StoredFile::factory()->create([
            'source_id' => 'pending-1',
            'variant' => 'original',
            'status' => StoredFileStatus::Pending->value,
        ]);
        StoredFile::factory()->create([
            'source_id' => 'completed-1',
            'variant' => 'thumbnail',
            'status' => StoredFileStatus::Completed->value,
        ]);

        $repository = app(StoredFileRepository::class);

        $this->assertSame([], $repository->completedOriginalSourceIds([]));
        $this->assertSame(['completed-1'], $repository->completedOriginalSourceIds(['completed-1', 'pending-1']));
        $this->assertSame([], $repository->originalsBySourceIds([])->all());
        $this->assertSame(['completed-1', 'pending-1'], $repository->originalsBySourceIds(['completed-1', 'pending-1'])->keys()->sort()->values()->all());
        $this->assertTrue($repository->hasCompletedOriginal('completed-1'));
        $this->assertFalse($repository->hasCompletedOriginal('pending-1'));
        $this->assertTrue($completed->is($repository->findOriginalBySourceId('flickr_photo', 'completed-1')));
        $this->assertTrue($completed->is($repository->findBySourceId('flickr_photo', 'completed-1')));
        $this->assertTrue($completed->is($repository->findById($completed->id)));
        $this->assertTrue($completed->is($repository->findByUuid($completed->uuid)));
        $this->assertNull($repository->findById(999999));
        $this->assertNull($repository->findByUuid('missing'));
    }

    public function test_first_or_create_and_pending_ensure_are_idempotent(): void
    {
        $repository = app(StoredFileRepository::class);
        $created = $repository->firstOrCreateOriginal('flickr_photo', 'photo-1', 'owner@N01');
        $same = $repository->firstOrCreateOriginal('flickr_photo', 'photo-1', 'other@N01');

        $this->assertTrue($created->is($same));
        $this->assertSame(StoredFileStatus::Pending->value, $created->status);

        $failed = StoredFile::factory()->create([
            'source_id' => 'photo-failed',
            'variant' => 'original',
            'status' => StoredFileStatus::Failed->value,
            'error_message' => 'old error',
        ]);
        $completed = StoredFile::factory()->create([
            'source_id' => 'photo-completed',
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
        ]);

        $repository->ensurePendingOriginals([]);
        $repository->ensurePendingOriginals([
            ['source_type' => 'flickr_photo', 'source_id' => '', 'source_owner' => 'owner@N01'],
        ]);
        $repository->ensurePendingOriginals([
            ['source_type' => 'flickr_photo', 'source_id' => 'photo-new', 'source_owner' => 'owner@N01'],
            ['source_type' => 'flickr_photo', 'source_id' => 'photo-failed', 'source_owner' => 'owner@N01'],
            ['source_type' => 'flickr_photo', 'source_id' => 'photo-completed', 'source_owner' => 'owner@N01'],
        ]);

        $this->assertDatabaseHas('stored_files', [
            'source_id' => 'photo-new',
            'variant' => 'original',
            'status' => StoredFileStatus::Pending->value,
        ]);
        $this->assertSame(StoredFileStatus::Pending->value, $failed->refresh()->status);
        $this->assertNull($failed->error_message);
        $this->assertSame(StoredFileStatus::Completed->value, $completed->refresh()->status);
    }

    public function test_status_mutations_counts_owner_queries_and_subquery(): void
    {
        Storage::fake();
        $repository = app(StoredFileRepository::class);
        $file = StoredFile::factory()->create([
            'source_id' => 'photo-state',
            'source_owner' => 'owner@N01',
            'variant' => 'original',
            'status' => StoredFileStatus::Pending->value,
            'local_path' => 'downloads/photo-state.jpg',
        ]);

        $repository->markDownloading('photo-state');
        $this->assertSame(StoredFileStatus::Downloading->value, $file->refresh()->status);

        $repository->markCompleted('photo-state', ['bytes' => 123]);
        $file->refresh();
        $this->assertSame(StoredFileStatus::Completed->value, $file->status);
        $this->assertSame(123, $file->bytes);

        $repository->markPending('photo-state', 'retry');
        $file->refresh();
        $this->assertSame(StoredFileStatus::Pending->value, $file->status);
        $this->assertSame('retry', $file->error_message);

        $repository->markFailed('photo-state', 'failed');
        $this->assertSame(StoredFileStatus::Failed->value, $file->refresh()->status);

        $repository->clearLocalPath($file);
        $this->assertNull($file->refresh()->local_path);
        $this->assertSame(1, $repository->countAll());
        $this->assertSame([], $repository->originalsForOwners([])->all());
        $this->assertCount(1, $repository->originalsForOwners(['owner@N01']));

        $repository->markCompleted('photo-state', []);
        $counts = $repository->completedOriginalCountSubquery()->get()->keyBy('contact_nsid');
        $this->assertSame(1, (int) $counts['owner@N01']->aggregate);
    }
}

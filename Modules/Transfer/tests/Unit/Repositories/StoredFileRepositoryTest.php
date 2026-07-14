<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Repositories;

use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Repositories\StoredFileRepository;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class StoredFileRepositoryTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_has_completed_original_returns_false_when_missing(): void
    {
        $repository = app(StoredFileRepository::class);

        $this->assertFalse($repository->hasCompletedOriginal('missing-photo'));
    }

    public function test_has_completed_original_returns_true_for_completed_variant(): void
    {
        StoredFile::query()->create([
            'flickr_photo_id' => 'photo-1',
            'owner_nsid' => 'owner@N01',
            'variant' => 'original',
            'status' => 'completed',
            'original_name' => 'photo-1_original.jpg',
        ]);

        $repository = app(StoredFileRepository::class);

        $this->assertTrue($repository->hasCompletedOriginal('photo-1'));
    }

    public function test_find_by_uuid_returns_matching_record(): void
    {
        $stored = StoredFile::query()->create([
            'flickr_photo_id' => 'photo-uuid',
            'owner_nsid' => 'owner@N01',
            'variant' => 'original',
            'status' => 'completed',
            'original_name' => 'photo-uuid_original.jpg',
        ]);

        $repository = app(StoredFileRepository::class);

        $this->assertTrue($stored->uuid !== null && $stored->uuid !== '');
        $this->assertSame($stored->id, $repository->findByUuid((string) $stored->uuid)?->id);
    }

    public function test_first_or_create_original_is_idempotent(): void
    {
        $repository = app(StoredFileRepository::class);

        $first = $repository->firstOrCreateOriginal('photo-2', 'owner@N01');
        $second = $repository->firstOrCreateOriginal('photo-2', 'owner@N01');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('pending', $second->status);
        $this->assertSame('photo-2_original', $second->original_name);
    }

    public function test_ensure_pending_originals_inserts_and_repends_failed(): void
    {
        $owner = FlickrNsid::fake();
        $failedId = (string) fake()->unique()->numerify('#########');
        $newId = (string) fake()->unique()->numerify('#########');
        $downloadingId = (string) fake()->unique()->numerify('#########');

        StoredFile::query()->create([
            'flickr_photo_id' => $failedId,
            'owner_nsid' => $owner,
            'variant' => 'original',
            'status' => StoredFileStatus::Failed->value,
            'original_name' => "{$failedId}_original",
            'error_message' => 'boom',
        ]);
        StoredFile::query()->create([
            'flickr_photo_id' => $downloadingId,
            'owner_nsid' => $owner,
            'variant' => 'original',
            'status' => StoredFileStatus::Downloading->value,
            'original_name' => "{$downloadingId}_original",
        ]);

        app(StoredFileRepository::class)->ensurePendingOriginals([
            ['flickr_photo_id' => $failedId, 'owner_nsid' => $owner],
            ['flickr_photo_id' => $newId, 'owner_nsid' => $owner],
            ['flickr_photo_id' => $downloadingId, 'owner_nsid' => $owner],
        ]);

        $this->assertDatabaseHas('stored_files', [
            'flickr_photo_id' => $newId,
            'variant' => 'original',
            'status' => StoredFileStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('stored_files', [
            'flickr_photo_id' => $failedId,
            'status' => StoredFileStatus::Pending->value,
            'error_message' => null,
        ]);
        $this->assertDatabaseHas('stored_files', [
            'flickr_photo_id' => $downloadingId,
            'status' => StoredFileStatus::Downloading->value,
        ]);
    }

    public function test_completed_original_flickr_photo_ids_and_originals_by_ids(): void
    {
        StoredFile::query()->create([
            'flickr_photo_id' => 'done-1',
            'owner_nsid' => FlickrNsid::fake(),
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
            'original_name' => 'done-1_original.jpg',
        ]);
        StoredFile::query()->create([
            'flickr_photo_id' => 'pending-1',
            'owner_nsid' => FlickrNsid::fake(),
            'variant' => 'medium',
            'status' => StoredFileStatus::Pending->value,
            'original_name' => 'pending-1_medium.jpg',
        ]);

        $repository = app(StoredFileRepository::class);

        $this->assertSame(['done-1'], $repository->completedOriginalFlickrPhotoIds(['done-1', 'pending-1']));
        $this->assertSame([], $repository->completedOriginalFlickrPhotoIds([]));
        $this->assertCount(1, $repository->originalsByFlickrPhotoIds(['done-1', 'pending-1']));
    }

    public function test_mark_status_helpers_update_original_rows(): void
    {
        StoredFile::query()->create([
            'flickr_photo_id' => 'mark-me',
            'owner_nsid' => FlickrNsid::fake(),
            'variant' => 'original',
            'status' => StoredFileStatus::Pending->value,
            'original_name' => 'mark-me_original.jpg',
        ]);

        $repository = app(StoredFileRepository::class);

        $repository->markDownloading('mark-me');
        $this->assertSame(StoredFileStatus::Downloading->value, $repository->findOriginalByFlickrPhotoId('mark-me')?->status);

        $repository->markCompleted('mark-me', ['local_path' => '/tmp/mark-me.jpg']);
        $stored = $repository->findOriginalByFlickrPhotoId('mark-me');
        $this->assertSame(StoredFileStatus::Completed->value, $stored?->status);
        $this->assertSame('/tmp/mark-me.jpg', $stored?->local_path);

        $repository->markPending('mark-me', 'retry later');
        $this->assertSame('retry later', $repository->findOriginalByFlickrPhotoId('mark-me')?->error_message);

        $repository->markFailed('mark-me', 'permanent failure');
        $this->assertSame(StoredFileStatus::Failed->value, $repository->findOriginalByFlickrPhotoId('mark-me')?->status);
    }

    public function test_originals_for_owners_and_count_all(): void
    {
        $owner = FlickrNsid::fake();

        StoredFile::query()->create([
            'flickr_photo_id' => 'owner-photo',
            'owner_nsid' => $owner,
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
            'original_name' => 'owner-photo_original.jpg',
            'local_path' => '/tmp/owner-photo.jpg',
        ]);

        $repository = app(StoredFileRepository::class);

        $this->assertSame(1, $repository->countAll());
        $this->assertCount(1, $repository->originalsForOwners([$owner]));
        $this->assertCount(0, $repository->originalsForOwners([]));
    }

    public function test_completed_original_count_subquery_groups_by_owner(): void
    {
        $owner = FlickrNsid::fake();

        StoredFile::query()->create([
            'flickr_photo_id' => 'subquery-1',
            'owner_nsid' => $owner,
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
            'original_name' => 'subquery-1_original.jpg',
        ]);
        StoredFile::query()->create([
            'flickr_photo_id' => 'subquery-2',
            'owner_nsid' => $owner,
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
            'original_name' => 'subquery-2_original.jpg',
        ]);

        $rows = app(StoredFileRepository::class)
            ->completedOriginalCountSubquery()
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame($owner, $rows->first()->contact_nsid);
        $this->assertSame(2, (int) $rows->first()->aggregate);
    }
}

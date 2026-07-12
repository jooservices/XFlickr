<?php

declare(strict_types=1);

namespace Tests\Feature;

use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Repositories\StoredFileRepository;
use Tests\Concerns\SafeRefreshDatabase;
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
}

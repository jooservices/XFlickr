<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StoredFile;
use App\Repositories\StoredFileRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StoredFileRepositoryTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_first_or_create_original_is_idempotent(): void
    {
        $repository = app(StoredFileRepository::class);

        $first = $repository->firstOrCreateOriginal('photo-2', 'owner@N01');
        $second = $repository->firstOrCreateOriginal('photo-2', 'owner@N01');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('pending', $second->status);
    }
}

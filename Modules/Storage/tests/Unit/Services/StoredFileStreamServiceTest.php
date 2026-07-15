<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Storage\Enums\StoredFileStatus;
use Modules\Storage\Models\StoredFile;
use Modules\Storage\Services\StoredFileStreamService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StoredFileStreamServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_find_viewable_original_returns_null_for_non_existent_uuid(): void
    {
        $service = app(StoredFileStreamService::class);

        $this->assertNull($service->findViewableOriginal(fake()->uuid()));
    }

    public function test_find_viewable_original_returns_null_for_non_completed_status(): void
    {
        $stored = StoredFile::factory()->create([
            'status' => StoredFileStatus::Pending->value,
        ]);

        $service = app(StoredFileStreamService::class);

        $this->assertNull($service->findViewableOriginal($stored->uuid));
    }

    public function test_find_viewable_original_returns_null_when_local_path_missing_on_disk(): void
    {
        Storage::fake();

        $stored = StoredFile::factory()->create([
            'status' => StoredFileStatus::Completed->value,
            'local_path' => 'downloads/nonexistent.jpg',
        ]);

        $service = app(StoredFileStreamService::class);

        $this->assertNull($service->findViewableOriginal($stored->uuid));
    }

    public function test_find_viewable_original_returns_model_when_completed_and_file_exists(): void
    {
        Storage::fake();

        $localPath = 'downloads/'.fake()->uuid().'.jpg';

        $stored = StoredFile::factory()->create([
            'status' => StoredFileStatus::Completed->value,
            'local_path' => $localPath,
        ]);

        Storage::put($localPath, 'image-content');

        $service = app(StoredFileStreamService::class);
        $result = $service->findViewableOriginal($stored->uuid);

        $this->assertNotNull($result);
        $this->assertSame($stored->id, $result->id);
    }

    public function test_mime_type_returns_stored_mime_type_when_present(): void
    {
        $stored = StoredFile::factory()->make([
            'mime_type' => 'image/png',
        ]);

        $service = app(StoredFileStreamService::class);

        $this->assertSame('image/png', $service->mimeType($stored));
    }

    public function test_mime_type_falls_back_to_octet_stream_for_empty_path(): void
    {
        $stored = StoredFile::factory()->make([
            'mime_type' => null,
            'local_path' => '',
        ]);

        $service = app(StoredFileStreamService::class);

        $this->assertSame('application/octet-stream', $service->mimeType($stored));
    }

    public function test_filename_returns_original_name_when_set(): void
    {
        $originalName = fake()->lexify('photo-????.jpg');
        $stored = StoredFile::factory()->make([
            'original_name' => $originalName,
        ]);

        $service = app(StoredFileStreamService::class);

        $this->assertSame($originalName, $service->filename($stored));
    }

    public function test_filename_returns_basename_of_local_path_when_original_name_empty(): void
    {
        $stored = StoredFile::factory()->make([
            'original_name' => '',
            'local_path' => 'downloads/some-image.jpg',
        ]);

        $service = app(StoredFileStreamService::class);

        $this->assertSame('some-image.jpg', $service->filename($stored));
    }

    public function test_filename_returns_photo_when_both_are_empty(): void
    {
        $stored = StoredFile::factory()->make([
            'original_name' => '',
            'local_path' => '',
        ]);

        $service = app(StoredFileStreamService::class);

        $this->assertSame('photo', $service->filename($stored));
    }
}

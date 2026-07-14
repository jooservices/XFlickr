<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Services\StoredFileStreamService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StoredFileStreamServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    private StoredFileStreamService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StoredFileStreamService::class);
    }

    public function test_find_viewable_original_returns_null_when_local_file_is_missing(): void
    {
        Storage::fake('local');

        $stored = StoredFile::query()->create([
            'flickr_photo_id' => 'missing-local',
            'owner_nsid' => '111@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/111@N01/photos/missing-local_abc.jpg',
            'original_name' => 'missing-local_original.jpg',
        ]);

        $this->assertNull($this->service->findViewableOriginal($stored->uuid));
    }

    public function test_mime_type_uses_detected_storage_mime_when_model_value_is_blank(): void
    {
        Storage::fake('local');
        Storage::put('flickr/111@N01/photos/detected.jpg', 'image-bytes');

        $stored = StoredFile::query()->create([
            'flickr_photo_id' => 'detected',
            'owner_nsid' => '111@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/111@N01/photos/detected.jpg',
            'original_name' => 'detected_original.jpg',
            'mime_type' => '',
        ]);

        $this->assertSame('image/jpeg', $this->service->mimeType($stored));
    }

    public function test_mime_type_falls_back_to_octet_stream_when_detection_fails(): void
    {
        $stored = StoredFile::query()->create([
            'flickr_photo_id' => 'no-path',
            'owner_nsid' => '111@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => '',
            'original_name' => 'no-path_original.jpg',
            'mime_type' => null,
        ]);

        $this->assertSame('application/octet-stream', $this->service->mimeType($stored));
    }

    public function test_filename_uses_local_path_basename_when_original_name_is_blank(): void
    {
        $stored = StoredFile::query()->create([
            'flickr_photo_id' => 'basename-photo',
            'owner_nsid' => '111@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/111@N01/photos/basename-photo_abc.jpg',
            'original_name' => '',
        ]);

        $this->assertSame('basename-photo_abc.jpg', $this->service->filename($stored));
    }

    public function test_filename_falls_back_to_photo_when_no_names_are_available(): void
    {
        $stored = StoredFile::query()->create([
            'flickr_photo_id' => 'fallback-photo',
            'owner_nsid' => '111@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => '',
            'original_name' => '',
        ]);

        $this->assertSame('photo', $this->service->filename($stored));
    }
}

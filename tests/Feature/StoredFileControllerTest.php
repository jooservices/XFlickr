<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Modules\Transfer\Models\StoredFile;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StoredFileControllerTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_it_streams_completed_stored_file_inline(): void
    {
        Storage::fake('local');
        Storage::put('flickr/111@N01/photos/p-1_abc.jpg', 'fake-image-bytes');

        $stored = StoredFile::query()->create([
            'flickr_photo_id' => 'p-1',
            'owner_nsid' => '111@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/111@N01/photos/p-1_abc.jpg',
            'original_name' => 'p-1_original.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $response = $this->get('/api/v1/stored-files/'.$stored->uuid);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('fake-image-bytes', $response->streamedContent());
    }

    public function test_it_returns_not_found_for_missing_or_incomplete_file(): void
    {
        Storage::fake('local');

        $stored = StoredFile::query()->create([
            'flickr_photo_id' => 'p-2',
            'owner_nsid' => '111@N01',
            'variant' => 'original',
            'status' => 'pending',
            'original_name' => 'p-2_original.jpg',
        ]);

        $this->getJson('/api/v1/stored-files/'.$stored->uuid)->assertNotFound();
        $this->getJson('/api/v1/stored-files/'.fake()->uuid())->assertNotFound();
    }
}

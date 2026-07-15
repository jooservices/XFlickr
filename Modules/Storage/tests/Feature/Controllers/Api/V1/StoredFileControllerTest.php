<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Feature\Controllers\Api\V1;

use Illuminate\Support\Facades\Storage;
use Modules\Storage\Enums\StoredFileStatus;
use Modules\Storage\Models\StoredFile;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StoredFileControllerTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_show_returns_404_for_missing_uuid(): void
    {
        $response = $this->getJson('/api/v1/stored-files/'.fake()->uuid());

        $response->assertNotFound();
    }

    public function test_show_streams_file_for_valid_stored_file(): void
    {
        Storage::fake();

        $localPath = 'downloads/'.fake()->uuid().'.jpg';
        Storage::put($localPath, 'fake-image-content');

        $stored = StoredFile::factory()->create([
            'status' => StoredFileStatus::Completed->value,
            'local_path' => $localPath,
            'mime_type' => 'image/jpeg',
            'original_name' => 'photo.jpg',
        ]);

        $response = $this->get('/api/v1/stored-files/'.$stored->uuid);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
    }
}

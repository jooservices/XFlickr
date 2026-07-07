<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StoredFile;
use App\Models\TransferBatch;
use App\Services\Flickr\ContactDownloadCountsService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class ContactDownloadCountsServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_it_counts_downloaded_files_from_status_without_local_disk_check(): void
    {
        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        StoredFile::query()->create([
            'flickr_photo_id' => 'photo-1',
            'owner_nsid' => 'friend@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/friend@N01/photos/photo-1_secret.jpg',
            'original_name' => 'photo-1.jpg',
        ]);

        StoredFile::query()->create([
            'flickr_photo_id' => 'photo-2',
            'owner_nsid' => 'friend@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/friend@N01/photos/photo-2_secret.jpg',
            'original_name' => 'photo-2.jpg',
        ]);

        StoredFile::query()->create([
            'flickr_photo_id' => 'photo-3',
            'owner_nsid' => 'friend@N01',
            'variant' => 'original',
            'status' => 'failed',
            'original_name' => 'photo-3.jpg',
        ]);

        $counts = app(ContactDownloadCountsService::class)->forContacts($connection, ['friend@N01']);

        $this->assertSame(2, $counts['friend@N01']['total']);
        $this->assertSame(1, $counts['friend@N01']['failed']);
        $this->assertFalse($counts['friend@N01']['processing']);
    }

    public function test_it_marks_running_download_batches_as_processing(): void
    {
        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'friend@N01',
            'status' => 'running',
            'total_count' => 10,
            'completed_count' => 4,
        ]);

        $counts = app(ContactDownloadCountsService::class)->forContacts($connection, ['friend@N01']);

        $this->assertTrue($counts['friend@N01']['processing']);
        $this->assertSame(4, $counts['friend@N01']['batch_completed']);
        $this->assertSame(10, $counts['friend@N01']['batch_total']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\DownloadContactsPhotosJob;
use App\Jobs\DownloadPhotoJob;
use App\Models\TransferBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use JOOservices\XFlickrCrawler\Models\Photo;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class DownloadContactsPhotosJobTest extends TestCase
{
    use CreatesFlickrConnection;
    use RefreshDatabase;

    public function test_job_queues_downloads_for_selected_contact_only(): void
    {
        Bus::fake([DownloadPhotoJob::class]);

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        Photo::query()->create([
            'flickr_photo_id' => 'p-self',
            'owner_nsid' => 'me@N01',
            'title' => 'Mine',
        ]);
        Photo::query()->create([
            'flickr_photo_id' => 'p-friend',
            'owner_nsid' => 'friend@N01',
            'title' => 'Theirs',
        ]);

        DownloadContactsPhotosJob::dispatchSync($connection->connection_key, 'friend@N01');

        $batch = TransferBatch::query()->first();
        $this->assertNotNull($batch);
        $this->assertSame(1, $batch->total_count);
        $this->assertDatabaseHas('transfer_items', ['flickr_photo_id' => 'p-friend']);
        $this->assertDatabaseMissing('transfer_items', ['flickr_photo_id' => 'p-self']);

        Bus::assertDispatched(DownloadPhotoJob::class, function (DownloadPhotoJob $job): bool {
            return true;
        });
    }
}

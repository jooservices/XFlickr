<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PhotoTransferExecutionOutcome;
use App\Models\StoredFile;
use App\Models\TransferBatch;
use App\Models\TransferItem;
use App\Services\Flickr\PhotoDownloadExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use JOOservices\XFlickrCrawler\Models\Photo;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class PhotoDownloadExecutionServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use RefreshDatabase;

    public function test_it_downloads_directly_into_flickr_owner_directory(): void
    {
        Storage::fake('local');

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        Photo::query()->create([
            'flickr_photo_id' => 'photo-1',
            'owner_nsid' => 'friend@N01',
            'title' => 'Test',
            'secret' => 'abc123',
            'server' => '65535',
            'raw_payload' => [
                'sizes' => [
                    ['label' => 'Large', 'source' => 'https://example.test/photo-1.jpg'],
                ],
            ],
        ]);

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'friend@N01',
            'status' => 'running',
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => 'pending',
        ]);

        Http::fake([
            'https://example.test/*' => Http::response('jpeg-bytes', 200),
        ]);

        $outcome = app(PhotoDownloadExecutionService::class)->execute(
            'photo-1',
            'friend@N01',
            $connection->connection_key,
            $batch->id,
            1,
            3,
        );

        $this->assertSame(PhotoTransferExecutionOutcome::Completed, $outcome);
        Storage::disk('local')->assertExists('flickr/friend@N01/photos/photo-1_abc123.jpg');
        Storage::disk('local')->assertMissing('temp/photo-1.tmp');
        $this->assertSame('completed', StoredFile::query()->first()->status);
        $this->assertSame('completed', $batch->fresh()->status);
    }

    public function test_it_short_circuits_when_already_downloaded(): void
    {
        Storage::fake('local');

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        Photo::query()->create([
            'flickr_photo_id' => 'photo-1',
            'owner_nsid' => 'friend@N01',
            'title' => 'Test',
            'secret' => 'abc123',
            'server' => '65535',
            'raw_payload' => [],
        ]);

        StoredFile::query()->create([
            'flickr_photo_id' => 'photo-1',
            'owner_nsid' => 'friend@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/friend@N01/photos/photo-1_abc123.jpg',
            'original_name' => 'photo-1_original.jpg',
        ]);

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'friend@N01',
            'status' => 'running',
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => 'pending',
        ]);

        Http::fake();

        $outcome = app(PhotoDownloadExecutionService::class)->execute(
            'photo-1',
            'friend@N01',
            $connection->connection_key,
            $batch->id,
            1,
            3,
        );

        $this->assertSame(PhotoTransferExecutionOutcome::Completed, $outcome);
        Http::assertNothingSent();
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame('completed', TransferItem::query()->first()->status);
    }
}

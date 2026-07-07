<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PhotoTransferExecutionOutcome;
use App\Models\StoredFile;
use App\Models\TransferBatch;
use App\Models\TransferItem;
use App\Services\Flickr\PhotoDownloadExecutionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use JOOservices\XFlickrCrawler\Models\Photo;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class PhotoDownloadExecutionServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

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
        );

        $this->assertSame(PhotoTransferExecutionOutcome::Completed, $outcome);
        Storage::disk('local')->assertExists('flickr/friend@N01/photos/photo-1_abc123.jpg');
        Storage::disk('local')->assertMissing('temp/photo-1.tmp');
        $this->assertSame('completed', StoredFile::query()->first()->status);
        $this->assertSame('photo-1_original.jpg', StoredFile::query()->first()->original_name);
        $this->assertSame('completed', $batch->fresh()->status);
    }

    public function test_it_uses_extension_from_download_url(): void
    {
        Storage::fake('local');

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        Photo::query()->create([
            'flickr_photo_id' => 'photo-png',
            'owner_nsid' => 'friend@N01',
            'title' => 'PNG',
            'secret' => 'sec456',
            'server' => '65535',
            'raw_payload' => [
                'sizes' => [
                    ['label' => 'Original', 'source' => 'https://example.test/photo-png.png'],
                ],
            ],
        ]);

        Http::fake([
            'https://example.test/*' => Http::response('png-bytes', 200),
        ]);

        $outcome = app(PhotoDownloadExecutionService::class)->execute(
            'photo-png',
            'friend@N01',
            $connection->connection_key,
            null,
        );

        $this->assertSame(PhotoTransferExecutionOutcome::Completed, $outcome);
        Storage::disk('local')->assertExists('flickr/friend@N01/photos/photo-png_sec456.png');

        $storedFile = StoredFile::query()->where('flickr_photo_id', 'photo-png')->first();
        $this->assertSame('photo-png_original.png', $storedFile->original_name);
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
        );

        $this->assertSame(PhotoTransferExecutionOutcome::Completed, $outcome);
        Http::assertNothingSent();
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame('completed', TransferItem::query()->first()->status);
    }

    public function test_it_releases_download_lock_only_once_when_already_downloaded(): void
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

        app(PhotoDownloadExecutionService::class)->execute(
            'photo-1',
            'friend@N01',
            $connection->connection_key,
            $batch->id,
        );

        $verifyLock = Cache::lock('download_lock:photo-1', 120);
        $this->assertTrue($verifyLock->get());
        $verifyLock->release();
    }
}

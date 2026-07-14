<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Crawler\Models\Photo;
use Modules\Transfer\Enums\PhotoTransferExecutionOutcome;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Services\PhotoDownloadExecutionService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class PhotoDownloadExecutionServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_it_downloads_directly_into_flickr_owner_directory(): void
    {
        Storage::fake('local');

        $connection = $this->createFlickrConnection();

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

        $connection = $this->createFlickrConnection();

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

        $connection = $this->createFlickrConnection();

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

        $connection = $this->createFlickrConnection();

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

    public function test_handle_failure_marks_stored_file_and_item_failed(): void
    {
        Storage::fake('local');

        $connection = $this->createFlickrConnection();

        Photo::query()->create([
            'flickr_photo_id' => 'photo-fail',
            'owner_nsid' => FlickrNsid::fake(),
            'title' => 'Fail',
            'secret' => 'abc123',
            'server' => '65535',
            'raw_payload' => [],
        ]);

        StoredFile::query()->create([
            'flickr_photo_id' => 'photo-fail',
            'owner_nsid' => FlickrNsid::fake(),
            'variant' => 'original',
            'status' => 'downloading',
            'local_path' => null,
            'original_name' => 'photo-fail_original.jpg',
        ]);

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => FlickrNsid::fake(),
            'status' => 'running',
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-fail',
            'status' => 'processing',
        ]);

        app(PhotoDownloadExecutionService::class)->handleFailure('photo-fail', $batch->id, 'download exploded');

        $this->assertSame('failed', StoredFile::query()->first()->status);
        $this->assertSame('failed', TransferItem::query()->first()->status);
    }

    public function test_it_defers_when_download_lock_is_held(): void
    {
        Storage::fake('local');

        $connection = $this->createFlickrConnection();
        $photoId = (string) fake()->numerify('########');

        Photo::query()->create([
            'flickr_photo_id' => $photoId,
            'owner_nsid' => FlickrNsid::fake(),
            'title' => 'Locked',
            'secret' => 'abc123',
            'server' => '65535',
            'raw_payload' => [],
        ]);

        $lock = Cache::lock("download_lock:{$photoId}", 120);
        $this->assertTrue($lock->get());

        $outcome = app(PhotoDownloadExecutionService::class)->execute(
            $photoId,
            FlickrNsid::fake(),
            $connection->connection_key,
            null,
        );

        $this->assertSame(PhotoTransferExecutionOutcome::Deferred, $outcome);

        $lock->release();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Modules\Crawler\Support\XFlickrConfig;
use Modules\Transfer\Jobs\DownloadPhotoJob;
use Modules\Transfer\Jobs\FanOutTransferBatchJob;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Services\PhotoDownloadService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class PhotoDownloadServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_it_groups_pending_downloads_by_photoset_gallery_and_loose_photos(): void
    {
        Bus::fake([DownloadPhotoJob::class]);

        $connection = $this->createFlickrConnection();

        $setPhoto = Photo::query()->create([
            'flickr_photo_id' => 'p-set',
            'owner_nsid' => $connection->connection_key,
            'title' => 'Set photo',
        ]);
        $galleryPhoto = Photo::query()->create([
            'flickr_photo_id' => 'p-gallery',
            'owner_nsid' => $connection->connection_key,
            'title' => 'Gallery photo',
        ]);
        $loosePhoto = Photo::query()->create([
            'flickr_photo_id' => 'p-loose',
            'owner_nsid' => $connection->connection_key,
            'title' => 'Loose photo',
        ]);

        $photoset = Photoset::query()->create([
            'flickr_photoset_id' => 'set-1',
            'owner_nsid' => $connection->connection_key,
            'title' => 'Travel',
            'photo_count' => 1,
        ]);
        $gallery = Gallery::query()->create([
            'flickr_gallery_id' => 'gal-1',
            'owner_nsid' => $connection->connection_key,
            'title' => 'Favorites',
            'photo_count' => 1,
        ]);

        DB::table(XFlickrConfig::table('photoset_photo'))->insert([
            'xflickr_photoset_id' => $photoset->id,
            'xflickr_photo_id' => $setPhoto->id,
            'discovered_at' => now(),
        ]);
        DB::table(XFlickrConfig::table('gallery_photo'))->insert([
            'xflickr_gallery_id' => $gallery->id,
            'xflickr_photo_id' => $galleryPhoto->id,
            'discovered_at' => now(),
        ]);

        $queued = app(PhotoDownloadService::class)->fanOutDownloads($connection, $connection->connection_key);

        $this->assertSame(3, $queued);
        $this->assertDatabaseHas('transfer_batches', [
            'group_type' => 'photoset',
            'group_id' => 'set-1',
            'group_label' => 'Travel',
            'total_count' => 1,
        ]);
        $this->assertDatabaseHas('transfer_batches', [
            'group_type' => 'gallery',
            'group_id' => 'gal-1',
            'group_label' => 'Favorites',
            'total_count' => 1,
        ]);
        $this->assertDatabaseHas('transfer_batches', [
            'group_type' => 'owner',
            'group_label' => 'Loose photos',
            'total_count' => 1,
        ]);

        Bus::assertDispatched(DownloadPhotoJob::class, 3);
        Bus::assertDispatched(DownloadPhotoJob::class, function (DownloadPhotoJob $job) use ($connection): bool {
            return $this->jobConnectionKey($job) === $connection->connection_key;
        });
    }

    public function test_it_dispatches_fan_out_job_for_owner_downloads(): void
    {
        Bus::fake([FanOutTransferBatchJob::class]);

        $connection = $this->createFlickrConnection();
        Photo::query()->create([
            'flickr_photo_id' => 'p-async',
            'owner_nsid' => $connection->connection_key,
            'title' => 'Async photo',
        ]);

        $queued = app(PhotoDownloadService::class)->queueDownloads($connection);

        $this->assertSame(1, $queued);
        Bus::assertDispatched(FanOutTransferBatchJob::class, function (FanOutTransferBatchJob $job) use ($connection): bool {
            return $this->fanOutJobConnectionKey($job) === $connection->connection_key;
        });
    }

    public function test_it_chunks_large_owner_download_sets(): void
    {
        Bus::fake([DownloadPhotoJob::class]);

        $connection = $this->createFlickrConnection();

        for ($index = 1; $index <= 251; $index++) {
            Photo::query()->create([
                'flickr_photo_id' => "p-chunk-{$index}",
                'owner_nsid' => $connection->connection_key,
                'title' => "Chunk photo {$index}",
            ]);
        }

        $queued = app(PhotoDownloadService::class)->fanOutDownloads($connection, $connection->connection_key);

        $this->assertSame(2, $queued);
        $this->assertDatabaseCount('transfer_batches', 2);
        $this->assertDatabaseHas('transfer_batches', [
            'type' => 'download',
            'total_count' => 250,
        ]);
        $this->assertDatabaseHas('transfer_batches', [
            'type' => 'download',
            'total_count' => 1,
        ]);
        Bus::assertDispatched(DownloadPhotoJob::class, 251);
    }

    private function fanOutJobConnectionKey(FanOutTransferBatchJob $job): string
    {
        $reflection = new \ReflectionProperty(FanOutTransferBatchJob::class, 'connectionKey');

        return (string) $reflection->getValue($job);
    }

    private function jobConnectionKey(DownloadPhotoJob $job): string
    {
        $reflection = new \ReflectionProperty(DownloadPhotoJob::class, 'connectionKey');

        return (string) $reflection->getValue($job);
    }

    public function test_it_returns_zero_when_no_pending_photos(): void
    {
        Bus::fake([DownloadPhotoJob::class]);

        $connection = $this->createFlickrConnection();

        $queued = app(PhotoDownloadService::class)->queueDownloads($connection);

        $this->assertSame(0, $queued);
        $this->assertSame(0, TransferBatch::query()->count());
    }

    public function test_it_queues_a_single_photo_download(): void
    {
        Bus::fake([DownloadPhotoJob::class]);

        $connection = $this->createFlickrConnection();
        Photo::query()->create([
            'flickr_photo_id' => 'p-single',
            'owner_nsid' => 'friend@N01',
            'title' => 'Single photo',
        ]);

        $queued = app(PhotoDownloadService::class)->queuePhotoDownload($connection, 'p-single');

        $this->assertSame(1, $queued);
        $this->assertDatabaseHas('transfer_batches', [
            'type' => 'download',
            'subject_nsid' => 'friend@N01',
            'total_count' => 1,
        ]);
        Bus::assertDispatched(DownloadPhotoJob::class, 1);
    }

    public function test_it_queues_from_input_and_returns_flash_result(): void
    {
        Bus::fake([DownloadPhotoJob::class]);

        $connection = $this->createFlickrConnection();
        Photo::query()->create([
            'flickr_photo_id' => 'p-input',
            'owner_nsid' => 'friend@N01',
            'title' => 'Input photo',
        ]);

        $result = app(PhotoDownloadService::class)->queueFromInput(
            $connection,
            flickrPhotoId: 'p-input',
        );

        $this->assertSame('success', $result->flashKey);
        $this->assertSame('Photo download queued.', $result->message);
        $this->assertSame(1, $result->queuedCount);
        Bus::assertDispatched(DownloadPhotoJob::class, 1);
    }

    public function test_it_queues_selected_photo_downloads_from_input(): void
    {
        Bus::fake([DownloadPhotoJob::class]);

        $connection = $this->createFlickrConnection();
        $ownerA = FlickrNsid::fake();
        $ownerB = FlickrNsid::fake();

        Photo::query()->create([
            'flickr_photo_id' => 'p-bulk-a',
            'owner_nsid' => $ownerA,
            'title' => 'Bulk A',
        ]);
        Photo::query()->create([
            'flickr_photo_id' => 'p-bulk-b',
            'owner_nsid' => $ownerB,
            'title' => 'Bulk B',
        ]);

        $result = app(PhotoDownloadService::class)->queueFromInput(
            $connection,
            flickrPhotoIds: ['p-bulk-a', 'p-bulk-b'],
        );

        $this->assertSame('success', $result->flashKey);
        $this->assertSame(2, $result->queuedCount);
        $this->assertStringContainsString('2 selected photo(s)', $result->message);
        Bus::assertDispatched(DownloadPhotoJob::class, 2);
    }
}

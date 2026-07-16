<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Database\Factories\Crawler\PhotoFactory;
use Illuminate\Support\Facades\Queue;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photoset;
use Modules\Storage\Models\StorageAccount;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Jobs\DownloadFileJob;
use Modules\Transfer\Jobs\FanOutTransferJob;
use Modules\Transfer\Jobs\UploadFileJob;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Services\PhotoTransferService;
use Modules\Transfer\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;

final class PhotoTransferServiceTest extends TestCase
{
    use CreatesFlickrConnection;

    public function test_resolve_storage_account_uses_requested_or_default_account(): void
    {
        $default = StorageAccount::factory()->googleDrive()->default()->create();
        $other = StorageAccount::factory()->r2()->create();
        $service = app(PhotoTransferService::class);

        $this->assertSame($other->id, $service->resolveStorageAccountId($other->id));
        $this->assertSame($default->id, $service->resolveStorageAccountId(null));
        $this->assertNull($service->resolveStorageAccountId(999999));
    }

    public function test_download_input_prioritizes_photo_ids_and_handles_missing_catalog_rows(): void
    {
        Queue::fake();
        $connection = $this->createFlickrConnection(['connection_key' => 'connection-a']);
        $photo = PhotoFactory::new()->create([
            'flickr_photo_id' => 'photo-1',
            'owner_nsid' => 'owner@N01',
        ]);
        $service = app(PhotoTransferService::class);

        $missing = $service->queuePhotoDownloads($connection, ['missing']);
        $queued = $service->queueDownloadsFromInput(
            $connection,
            flickrPhotoId: 'ignored',
            flickrPhotoIds: [$photo->flickr_photo_id],
        );

        $this->assertSame('error', $missing->flashKey);
        $this->assertSame('success', $queued->flashKey);
        $this->assertSame(1, $queued->queuedCount);
        Queue::assertPushed(DownloadFileJob::class, 1);
    }

    public function test_download_input_reports_already_downloaded_and_fans_out_contacts(): void
    {
        Queue::fake();
        $connection = $this->createFlickrConnection(['connection_key' => 'connection-a']);
        $photo = PhotoFactory::new()->create([
            'flickr_photo_id' => 'photo-completed',
            'owner_nsid' => 'contact-a@N01',
        ]);
        StoredFile::factory()->create([
            'source_id' => $photo->flickr_photo_id,
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
        ]);
        $service = app(PhotoTransferService::class);

        $completed = $service->queueDownloadsFromInput($connection, flickrPhotoId: $photo->flickr_photo_id);
        $contacts = $service->queueDownloadsFromInput(
            $connection,
            contactNsids: ['contact-a@N01', 'missing@N01'],
        );
        $owner = $service->queueDownloadsFromInput($connection, contactNsid: 'contact-a@N01');

        $this->assertSame('success', $completed->flashKey);
        $this->assertSame(0, $completed->queuedCount);
        $this->assertSame(1, $contacts->queuedCount);
        $this->assertSame(1, $owner->queuedCount);
        Queue::assertPushed(FanOutTransferJob::class, 2);
    }

    public function test_fan_out_download_groups_photoset_gallery_and_loose_photos(): void
    {
        Queue::fake();
        $connection = $this->createFlickrConnection(['connection_key' => 'connection-a']);
        $photosetPhoto = PhotoFactory::new()->create(['owner_nsid' => 'owner@N01']);
        $galleryPhoto = PhotoFactory::new()->create(['owner_nsid' => 'owner@N01']);
        PhotoFactory::new()->create(['owner_nsid' => 'owner@N01']);

        $photoset = Photoset::query()->create([
            'flickr_photoset_id' => 'set-1',
            'owner_nsid' => 'owner@N01',
            'title' => 'Set A',
            'photo_count' => 1,
        ]);
        $photoset->photos()->attach($photosetPhoto->id, ['discovered_at' => now()]);

        $gallery = Gallery::query()->create([
            'flickr_gallery_id' => 'gallery-1',
            'owner_nsid' => 'owner@N01',
            'title' => 'Gallery A',
            'photo_count' => 1,
        ]);
        $gallery->photos()->attach($galleryPhoto->id, ['discovered_at' => now()]);

        $count = app(PhotoTransferService::class)->fanOutDownloads($connection, 'owner@N01');

        $this->assertSame(3, $count);
        $this->assertDatabaseCount('transfer_batches', 3);
        $this->assertDatabaseHas('transfer_batches', ['group_type' => 'photoset', 'group_id' => 'set-1']);
        $this->assertDatabaseHas('transfer_batches', ['group_type' => 'gallery', 'group_id' => 'gallery-1']);
        $this->assertDatabaseHas('transfer_batches', ['group_type' => 'owner', 'group_id' => 'owner@N01']);
        Queue::assertPushed(DownloadFileJob::class, 3);
    }

    public function test_upload_input_requires_storage_and_queues_downloads_before_uploads(): void
    {
        Queue::fake();
        $connection = $this->createFlickrConnection(['connection_key' => 'connection-a']);
        $photo = PhotoFactory::new()->create([
            'flickr_photo_id' => 'photo-needs-download',
            'owner_nsid' => 'owner@N01',
        ]);
        $service = app(PhotoTransferService::class);

        $missingStorage = $service->queueUploadsFromInput($connection, null, flickrPhotoId: $photo->flickr_photo_id);
        $account = StorageAccount::factory()->googleDrive()->default()->create();
        $queuedDownload = $service->queueUploadsFromInput(
            $connection,
            $account->id,
            flickrPhotoIds: [$photo->flickr_photo_id],
        );

        $this->assertSame('error', $missingStorage->flashKey);
        $this->assertSame('success', $queuedDownload->flashKey);
        $this->assertSame(0, $queuedDownload->queuedCount);
        Queue::assertPushed(DownloadFileJob::class, 1);
        Queue::assertNotPushed(UploadFileJob::class);
    }

    public function test_upload_input_queues_completed_files_for_single_and_contact_paths(): void
    {
        Queue::fake();
        $connection = $this->createFlickrConnection(['connection_key' => 'connection-a']);
        $account = StorageAccount::factory()->googleDrive()->default()->create();
        $photo = PhotoFactory::new()->create([
            'flickr_photo_id' => 'photo-ready',
            'owner_nsid' => 'owner@N01',
        ]);
        StoredFile::factory()->create([
            'source_id' => $photo->flickr_photo_id,
            'source_owner' => 'owner@N01',
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
        ]);
        $service = app(PhotoTransferService::class);

        $single = $service->queueUploadsFromInput(
            $connection,
            $account->id,
            flickrPhotoId: $photo->flickr_photo_id,
            deleteLocalAfterUpload: true,
        );
        $contacts = $service->queueUploadsFromInput(
            $connection,
            $account->id,
            contactNsids: ['owner@N01', 'missing@N01'],
        );
        $missingContact = $service->queueContactUploads($connection, 'missing@N01', $account->id);

        $this->assertSame(1, $single->queuedCount);
        $this->assertSame(1, $contacts->queuedCount);
        $this->assertSame('error', $missingContact->flashKey);
        Queue::assertPushed(UploadFileJob::class, 2);
    }

    public function test_fan_out_upload_dispatches_only_when_owner_has_photos(): void
    {
        Queue::fake();
        $connection = $this->createFlickrConnection(['connection_key' => 'connection-a']);
        $account = StorageAccount::factory()->googleDrive()->create();
        PhotoFactory::new()->create(['owner_nsid' => 'owner@N01']);
        $service = app(PhotoTransferService::class);

        $this->assertSame(1, $service->queueFanOutUpload($connection, 'owner@N01', $account->id, true));
        $this->assertSame(0, $service->queueFanOutUpload($connection, 'missing@N01', $account->id));
        Queue::assertPushed(FanOutTransferJob::class, 1);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Illuminate\Support\Facades\Bus;
use Modules\Crawler\Models\Photo;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageUpload;
use Modules\Transfer\Jobs\DownloadPhotoJob;
use Modules\Transfer\Jobs\FanOutTransferBatchJob;
use Modules\Transfer\Jobs\UploadPhotoJob;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Services\PhotoUploadService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class PhotoUploadServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_it_queues_uploads_for_contact_photos(): void
    {
        Bus::fake([DownloadPhotoJob::class, UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection();
        $storageAccount = $this->createStorageAccount();

        Photo::query()->create([
            'flickr_photo_id' => 'p-contact-1',
            'owner_nsid' => 'friend@N01',
            'title' => 'Contact photo 1',
        ]);
        StoredFile::query()->create([
            'flickr_photo_id' => 'p-contact-1',
            'owner_nsid' => 'friend@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/friend@N01/photos/p-contact-1_abc.jpg',
            'original_name' => 'p-contact-1_original.jpg',
        ]);
        Photo::query()->create([
            'flickr_photo_id' => 'p-contact-2',
            'owner_nsid' => 'friend@N01',
            'title' => 'Contact photo 2',
        ]);
        StoredFile::query()->create([
            'flickr_photo_id' => 'p-contact-2',
            'owner_nsid' => 'friend@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/friend@N01/photos/p-contact-2_abc.jpg',
            'original_name' => 'p-contact-2_original.jpg',
        ]);

        $queued = app(PhotoUploadService::class)->fanOutUploads($connection, $storageAccount, 'friend@N01');

        $this->assertSame(2, $queued);
        $this->assertDatabaseHas('transfer_batches', [
            'type' => 'upload',
            'connection_key' => $connection->connection_key,
            'storage_account_id' => $storageAccount->id,
            'subject_nsid' => 'friend@N01',
            'total_count' => 2,
        ]);
        Bus::assertDispatched(UploadPhotoJob::class, 2);
        Bus::assertNotDispatched(DownloadPhotoJob::class);
    }

    public function test_it_skips_photos_with_completed_uploads_for_storage_account(): void
    {
        Bus::fake([UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection();
        $storageAccount = $this->createStorageAccount();

        Photo::query()->create([
            'flickr_photo_id' => 'p-uploaded',
            'owner_nsid' => 'friend@N01',
            'title' => 'Already uploaded',
        ]);
        Photo::query()->create([
            'flickr_photo_id' => 'p-pending',
            'owner_nsid' => 'friend@N01',
            'title' => 'Pending upload',
        ]);

        $storedFile = StoredFile::query()->create([
            'flickr_photo_id' => 'p-uploaded',
            'owner_nsid' => 'friend@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/friend@N01/photos/p-uploaded_abc.jpg',
            'original_name' => 'p-uploaded_original.jpg',
        ]);

        StorageUpload::query()->create([
            'stored_file_id' => $storedFile->id,
            'storage_account_id' => $storageAccount->id,
            'status' => 'completed',
            'remote_file_id' => 'remote-123',
        ]);
        StoredFile::query()->create([
            'flickr_photo_id' => 'p-pending',
            'owner_nsid' => 'friend@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/friend@N01/photos/p-pending_abc.jpg',
            'original_name' => 'p-pending_original.jpg',
        ]);

        $queued = app(PhotoUploadService::class)->fanOutUploads($connection, $storageAccount, 'friend@N01');

        $this->assertSame(1, $queued);
        $this->assertDatabaseHas('transfer_items', [
            'flickr_photo_id' => 'p-pending',
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('transfer_items', [
            'flickr_photo_id' => 'p-uploaded',
        ]);
        Bus::assertDispatched(UploadPhotoJob::class, 1);
    }

    public function test_it_queues_download_batch_for_photos_missing_local_files_without_upload_items(): void
    {
        Bus::fake([DownloadPhotoJob::class, UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection();
        $storageAccount = $this->createStorageAccount();

        Photo::query()->create([
            'flickr_photo_id' => 'p-missing',
            'owner_nsid' => 'friend@N01',
            'title' => 'Needs download',
        ]);

        $queued = app(PhotoUploadService::class)->fanOutUploads($connection, $storageAccount, 'friend@N01');

        $this->assertSame(0, $queued);
        $this->assertDatabaseHas('transfer_batches', [
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'friend@N01',
            'total_count' => 1,
        ]);
        $this->assertDatabaseMissing('transfer_batches', [
            'type' => 'upload',
            'connection_key' => $connection->connection_key,
        ]);
        $this->assertDatabaseHas('transfer_items', [
            'flickr_photo_id' => 'p-missing',
            'status' => 'pending',
        ]);
        Bus::assertDispatched(DownloadPhotoJob::class, 1);
        Bus::assertNotDispatched(UploadPhotoJob::class);
    }

    public function test_it_dispatches_fan_out_job_for_owner_uploads(): void
    {
        Bus::fake([FanOutTransferBatchJob::class]);

        $connection = $this->createFlickrConnection();
        $storageAccount = $this->createStorageAccount();

        Photo::query()->create([
            'flickr_photo_id' => 'p-async-upload',
            'owner_nsid' => 'friend@N01',
            'title' => 'Async upload photo',
        ]);

        $queued = app(PhotoUploadService::class)->queueUploads($connection, $storageAccount, 'friend@N01');

        $this->assertSame(1, $queued);
        Bus::assertDispatched(FanOutTransferBatchJob::class);
    }

    public function test_it_returns_zero_when_no_pending_photos(): void
    {
        Bus::fake([UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection();
        $storageAccount = $this->createStorageAccount();

        $queued = app(PhotoUploadService::class)->queueUploads($connection, $storageAccount, 'friend@N01');

        $this->assertSame(0, $queued);
        $this->assertSame(0, TransferBatch::query()->count());
        Bus::assertNothingDispatched();
    }

    public function test_it_resolves_explicit_or_default_storage_account(): void
    {
        $defaultAccount = $this->createStorageAccount(['label' => 'Default Photos', 'is_default' => true]);
        $explicitAccount = $this->createStorageAccount(['label' => 'Archive Photos']);
        $service = app(PhotoUploadService::class);

        $this->assertTrue($explicitAccount->is($service->resolveStorageAccount($explicitAccount->id)));
        $this->assertTrue($defaultAccount->is($service->resolveStorageAccount()));
    }

    public function test_it_returns_error_result_when_queueing_from_input_without_storage_account(): void
    {
        $connection = $this->createFlickrConnection();

        $result = app(PhotoUploadService::class)->queueFromInput($connection);

        $this->assertSame('error', $result->flashKey);
        $this->assertSame('No storage account configured.', $result->message);
        $this->assertSame(0, $result->queuedCount);
    }

    public function test_it_queues_upload_from_input_and_returns_flash_result(): void
    {
        Bus::fake([UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection();
        $storageAccount = $this->createStorageAccount(['is_default' => true]);

        Photo::query()->create([
            'flickr_photo_id' => 'p-input-upload',
            'owner_nsid' => $connection->connection_key,
            'title' => 'Input upload',
        ]);
        StoredFile::query()->create([
            'flickr_photo_id' => 'p-input-upload',
            'owner_nsid' => $connection->connection_key,
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/'.$connection->connection_key.'/photos/p-input-upload_abc.jpg',
            'original_name' => 'p-input-upload_original.jpg',
        ]);

        $result = app(PhotoUploadService::class)->queueFromInput(
            $connection,
            storageAccountId: $storageAccount->id,
            flickrPhotoId: 'p-input-upload',
        );

        $this->assertSame('success', $result->flashKey);
        $this->assertSame('Photo upload queued.', $result->message);
        $this->assertSame(1, $result->queuedCount);
        Bus::assertDispatched(UploadPhotoJob::class, 1);
    }

    public function test_it_queues_selected_photo_uploads_from_input(): void
    {
        Bus::fake([UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection();
        $storageAccount = $this->createStorageAccount(['is_default' => true]);
        $ownerA = FlickrNsid::fake();
        $ownerB = FlickrNsid::fake();

        foreach ([['p-bulk-up-a', $ownerA], ['p-bulk-up-b', $ownerB]] as [$photoId, $ownerNsid]) {
            Photo::query()->create([
                'flickr_photo_id' => $photoId,
                'owner_nsid' => $ownerNsid,
                'title' => $photoId,
            ]);
            StoredFile::query()->create([
                'flickr_photo_id' => $photoId,
                'owner_nsid' => $ownerNsid,
                'variant' => 'original',
                'status' => 'completed',
                'local_path' => 'flickr/'.$ownerNsid.'/photos/'.$photoId.'_abc.jpg',
                'original_name' => $photoId.'_original.jpg',
            ]);
        }

        $result = app(PhotoUploadService::class)->queueFromInput(
            $connection,
            storageAccountId: $storageAccount->id,
            flickrPhotoIds: ['p-bulk-up-a', 'p-bulk-up-b'],
        );

        $this->assertSame('success', $result->flashKey);
        $this->assertSame(2, $result->queuedCount);
        $this->assertStringContainsString('2 selected', $result->message);
        Bus::assertDispatched(UploadPhotoJob::class, 2);
    }

    public function test_queue_from_input_reports_no_pending_upload_for_contact_list(): void
    {
        Bus::fake([FanOutTransferBatchJob::class]);

        $connection = $this->createFlickrConnection();
        $this->createStorageAccount(['is_default' => true]);
        $contactNsid = FlickrNsid::fake();

        $result = app(PhotoUploadService::class)->queueFromInput(
            $connection,
            contactNsids: [$contactNsid],
        );

        $this->assertSame('success', $result->flashKey);
        $this->assertSame('No photos pending upload.', $result->message);
        $this->assertSame(0, $result->queuedCount);
    }

    public function test_queue_from_input_reports_no_upload_for_unknown_photo_id(): void
    {
        $connection = $this->createFlickrConnection();
        $this->createStorageAccount(['is_default' => true]);

        $result = app(PhotoUploadService::class)->queueFromInput(
            $connection,
            flickrPhotoId: 'missing-photo-id',
        );

        $this->assertSame('success', $result->flashKey);
        $this->assertSame('No upload queued for this photo.', $result->message);
        $this->assertSame(0, $result->queuedCount);
    }

    public function test_queue_from_input_reports_no_upload_for_unknown_selected_photos(): void
    {
        $connection = $this->createFlickrConnection();
        $this->createStorageAccount(['is_default' => true]);

        $result = app(PhotoUploadService::class)->queueFromInput(
            $connection,
            flickrPhotoIds: ['missing-a', 'missing-b'],
        );

        $this->assertSame('success', $result->flashKey);
        $this->assertSame('No upload queued for selected photo(s).', $result->message);
        $this->assertSame(0, $result->queuedCount);
    }

    public function test_queue_from_input_reports_contact_upload_message_when_contact_has_pending_photos(): void
    {
        Bus::fake([FanOutTransferBatchJob::class]);

        $connection = $this->createFlickrConnection();
        $this->createStorageAccount(['is_default' => true]);
        $contactNsid = FlickrNsid::fake();

        Photo::query()->create([
            'flickr_photo_id' => 'contact-upload-photo',
            'owner_nsid' => $contactNsid,
            'title' => 'Contact upload',
        ]);

        $result = app(PhotoUploadService::class)->queueFromInput(
            $connection,
            contactNsid: $contactNsid,
        );

        $this->assertSame('success', $result->flashKey);
        $this->assertSame('1 photo(s) queued for upload.', $result->message);
        $this->assertSame(1, $result->queuedCount);
    }

    public function test_queue_from_input_reports_contact_list_upload_message_when_photos_are_pending(): void
    {
        Bus::fake([FanOutTransferBatchJob::class]);

        $connection = $this->createFlickrConnection();
        $this->createStorageAccount(['is_default' => true]);
        $contactNsid = FlickrNsid::fake();

        Photo::query()->create([
            'flickr_photo_id' => 'list-upload-photo',
            'owner_nsid' => $contactNsid,
            'title' => 'List upload',
        ]);

        $result = app(PhotoUploadService::class)->queueFromInput(
            $connection,
            contactNsids: [$contactNsid],
        );

        $this->assertSame('success', $result->flashKey);
        $this->assertSame('1 photo(s) queued for upload across 1 contact(s).', $result->message);
        $this->assertSame(1, $result->queuedCount);
    }

    public function test_queue_photo_uploads_returns_zero_when_no_matching_photos_exist(): void
    {
        $connection = $this->createFlickrConnection();
        $storageAccount = $this->createStorageAccount();

        $queued = app(PhotoUploadService::class)->queuePhotoUploads(
            $connection,
            $storageAccount,
            ['missing-photo-id'],
        );

        $this->assertSame(0, $queued);
    }

    public function test_fan_out_uploads_defaults_to_connection_owner_when_subject_is_null(): void
    {
        Bus::fake([DownloadPhotoJob::class, UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection();
        $storageAccount = $this->createStorageAccount();

        Photo::query()->create([
            'flickr_photo_id' => 'owner-upload-photo',
            'owner_nsid' => $connection->connection_key,
            'title' => 'Owner upload',
        ]);
        StoredFile::query()->create([
            'flickr_photo_id' => 'owner-upload-photo',
            'owner_nsid' => $connection->connection_key,
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/'.$connection->connection_key.'/photos/owner-upload-photo_abc.jpg',
            'original_name' => 'owner-upload-photo_original.jpg',
        ]);

        $queued = app(PhotoUploadService::class)->fanOutUploads($connection, $storageAccount);

        $this->assertSame(1, $queued);
        Bus::assertDispatched(UploadPhotoJob::class, 1);
    }

    public function test_queue_photo_upload_queues_download_when_stored_file_is_incomplete(): void
    {
        Bus::fake([DownloadPhotoJob::class, UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection();
        $storageAccount = $this->createStorageAccount();

        Photo::query()->create([
            'flickr_photo_id' => 'incomplete-upload',
            'owner_nsid' => 'friend@N01',
            'title' => 'Incomplete stored file',
        ]);
        StoredFile::query()->create([
            'flickr_photo_id' => 'incomplete-upload',
            'owner_nsid' => 'friend@N01',
            'variant' => 'original',
            'status' => 'pending',
            'original_name' => 'incomplete-upload_original.jpg',
        ]);

        $queued = app(PhotoUploadService::class)->queuePhotoUpload(
            $connection,
            $storageAccount,
            'incomplete-upload',
        );

        $this->assertSame(0, $queued);
        Bus::assertDispatched(DownloadPhotoJob::class, 1);
        Bus::assertNotDispatched(UploadPhotoJob::class);
    }

    public function test_queue_photo_upload_queues_download_when_original_file_is_missing(): void
    {
        Bus::fake([DownloadPhotoJob::class, UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection();
        $storageAccount = $this->createStorageAccount();

        Photo::query()->create([
            'flickr_photo_id' => 'missing-original',
            'owner_nsid' => 'friend@N01',
            'title' => 'Missing stored file',
        ]);

        $queued = app(PhotoUploadService::class)->queuePhotoUpload(
            $connection,
            $storageAccount,
            'missing-original',
        );

        $this->assertSame(0, $queued);
        Bus::assertDispatched(DownloadPhotoJob::class, 1);
        Bus::assertNotDispatched(UploadPhotoJob::class);
    }

    public function test_queue_from_input_reports_account_upload_message_for_connection_owner(): void
    {
        Bus::fake([FanOutTransferBatchJob::class]);

        $connection = $this->createFlickrConnection();
        $this->createStorageAccount(['is_default' => true]);

        Photo::query()->create([
            'flickr_photo_id' => 'account-upload',
            'owner_nsid' => $connection->connection_key,
            'title' => 'Account upload',
        ]);

        $result = app(PhotoUploadService::class)->queueFromInput($connection);

        $this->assertSame('success', $result->flashKey);
        $this->assertSame('Account photo upload queued.', $result->message);
        $this->assertSame(1, $result->queuedCount);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createStorageAccount(array $attributes = []): StorageAccount
    {
        return StorageAccount::query()->create(array_merge([
            'provider' => 'google_photos',
            'label' => 'Photos',
            'credentials' => [
                'access_token' => 'token',
                'refresh_token' => 'refresh',
            ],
            'connected_at' => now(),
        ], $attributes));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\DownloadPhotoJob;
use App\Jobs\UploadPhotoJob;
use App\Models\StorageAccount;
use App\Models\StorageUpload;
use App\Models\StoredFile;
use App\Models\TransferBatch;
use App\Services\Flickr\PhotoUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use JOOservices\XFlickrCrawler\Models\Photo;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class PhotoUploadServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use RefreshDatabase;

    public function test_it_queues_uploads_for_contact_photos(): void
    {
        Bus::fake([DownloadPhotoJob::class, UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);
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

        $queued = app(PhotoUploadService::class)->queueUploads($connection, $storageAccount, 'friend@N01');

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

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);
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

        $queued = app(PhotoUploadService::class)->queueUploads($connection, $storageAccount, 'friend@N01');

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

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);
        $storageAccount = $this->createStorageAccount();

        Photo::query()->create([
            'flickr_photo_id' => 'p-missing',
            'owner_nsid' => 'friend@N01',
            'title' => 'Needs download',
        ]);

        $queued = app(PhotoUploadService::class)->queueUploads($connection, $storageAccount, 'friend@N01');

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

    public function test_it_returns_zero_when_no_pending_photos(): void
    {
        Bus::fake([UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);
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
        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        $result = app(PhotoUploadService::class)->queueFromInput($connection);

        $this->assertSame('error', $result->flashKey);
        $this->assertSame('No storage account configured.', $result->message);
        $this->assertSame(0, $result->queuedCount);
    }

    public function test_it_queues_upload_from_input_and_returns_flash_result(): void
    {
        Bus::fake([UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);
        $storageAccount = $this->createStorageAccount(['is_default' => true]);

        Photo::query()->create([
            'flickr_photo_id' => 'p-input-upload',
            'owner_nsid' => 'me@N01',
            'title' => 'Input upload',
        ]);
        StoredFile::query()->create([
            'flickr_photo_id' => 'p-input-upload',
            'owner_nsid' => 'me@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/me@N01/photos/p-input-upload_abc.jpg',
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

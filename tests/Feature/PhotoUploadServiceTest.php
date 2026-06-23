<?php

declare(strict_types=1);

namespace Tests\Feature;

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
        Bus::fake([UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);
        $storageAccount = $this->createStorageAccount();

        Photo::query()->create([
            'flickr_photo_id' => 'p-contact-1',
            'owner_nsid' => 'friend@N01',
            'title' => 'Contact photo 1',
        ]);
        Photo::query()->create([
            'flickr_photo_id' => 'p-contact-2',
            'owner_nsid' => 'friend@N01',
            'title' => 'Contact photo 2',
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

    private function createStorageAccount(): StorageAccount
    {
        return StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Photos',
            'credentials' => [
                'access_token' => 'token',
                'refresh_token' => 'refresh',
            ],
            'connected_at' => now(),
        ]);
    }
}

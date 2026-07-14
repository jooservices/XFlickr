<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Bus;
use Modules\Crawler\Models\Photo;
use Modules\Storage\Models\StorageAccount;
use Modules\Transfer\Jobs\UploadPhotoJob;
use Modules\Transfer\Models\StoredFile;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class PhotoUploadControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_store_queues_upload_for_single_photo(): void
    {
        Bus::fake([UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection();
        $storageAccount = $this->createStorageAccount();

        Photo::query()->create([
            'flickr_photo_id' => 'upload-photo-1',
            'owner_nsid' => 'friend@N01',
            'title' => 'Upload me',
        ]);
        StoredFile::query()->create([
            'flickr_photo_id' => 'upload-photo-1',
            'owner_nsid' => 'friend@N01',
            'variant' => 'original',
            'status' => 'completed',
            'original_name' => 'upload-photo-1_original.jpg',
        ]);

        $response = $this->from('/flickr/accounts/'.$connection->public_id)
            ->post('/flickr/accounts/'.$connection->public_id.'/upload', [
                'storage_account_id' => $storageAccount->id,
                'flickr_photo_id' => 'upload-photo-1',
                'contact_nsid' => 'friend@N01',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        Bus::assertDispatched(UploadPhotoJob::class);
    }

    public function test_store_returns_error_when_select_all_matches_no_contacts(): void
    {
        Bus::fake([UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection();
        $storageAccount = $this->createStorageAccount();

        $response = $this->from('/flickr/accounts/'.$connection->public_id)
            ->post('/flickr/accounts/'.$connection->public_id.'/upload', [
                'storage_account_id' => $storageAccount->id,
                'select_all' => true,
                'search' => 'no-such-contact',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'No contacts matched the current filters.');
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
                'client_id' => 'client',
                'client_secret' => 'secret',
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
            'connected_at' => now(),
        ]);
    }
}

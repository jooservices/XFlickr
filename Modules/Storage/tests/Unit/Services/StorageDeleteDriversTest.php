<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use GuzzleHttp\Psr7\Response;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageRemoteItem;
use Modules\Storage\Models\StorageUpload;
use Modules\Storage\Models\StoredFile;
use Modules\Storage\Services\GoogleDrive\DeleteService as GoogleDriveDeleteService;
use Modules\Storage\Services\R2\DeleteService as R2DeleteService;
use Modules\Storage\Services\StorageDeleteService;
use Modules\Storage\Services\StorageDriverRegistry;
use Modules\Storage\Support\StorageR2Config;
use Modules\Storage\Tests\TestCase;

final class StorageDeleteDriversTest extends TestCase
{
    public function test_r2_delete_driver_purges_cache_when_items_deleted(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $itemId = 'album/photo.jpg';
        $key = StorageR2Config::from($account->credentials ?? [])->objectKey($itemId);

        StorageRemoteItem::factory()->create([
            'storage_account_id' => $account->id,
            'remote_id' => $itemId,
            'parent_remote_id' => '',
        ]);

        $storedFile = StoredFile::factory()->create();
        StorageUpload::factory()->create([
            'storage_account_id' => $account->id,
            'stored_file_id' => $storedFile->id,
            'remote_file_id' => $itemId,
            'remote_path' => $itemId,
            'status' => 'completed',
        ]);

        ['disk' => $disk] = $this->bindInMemoryDisk();
        $disk->put($key, 'photo-bytes');

        $driver = app(StorageDriverRegistry::class)->deleteDriver(StorageDriver::R2);
        $this->assertInstanceOf(R2DeleteService::class, $driver);

        $result = app(StorageDeleteService::class)->deleteMany($account, StorageDriver::R2, [$itemId]);

        $this->assertSame([$itemId], $result['deleted']);
        $this->assertFalse($disk->exists($key));
        $this->assertDatabaseMissing('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => $itemId,
        ]);
        $this->assertDatabaseMissing('storage_uploads', [
            'storage_account_id' => $account->id,
            'remote_file_id' => $itemId,
        ]);
    }

    public function test_google_drive_delete_driver_purges_cache_when_items_deleted(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $remoteId = fake()->uuid();

        StorageRemoteItem::factory()->create([
            'storage_account_id' => $account->id,
            'remote_id' => $remoteId,
            'parent_remote_id' => '',
        ]);

        $this->bindGoogleClient([
            new Response(204),
        ]);

        $driver = app(StorageDriverRegistry::class)->deleteDriver(StorageDriver::GoogleDrive);
        $this->assertInstanceOf(GoogleDriveDeleteService::class, $driver);

        $result = app(StorageDeleteService::class)->deleteMany(
            $account,
            StorageDriver::GoogleDrive,
            [$remoteId],
        );

        $this->assertSame([$remoteId], $result['deleted']);
        $this->assertDatabaseMissing('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => $remoteId,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Google\Client as GoogleClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Filesystem\Filesystem;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageRemoteItem;
use Modules\Storage\Models\StorageUpload;
use Modules\Storage\Services\GoogleDrive\DeleteService as GoogleDriveDeleteService;
use Modules\Storage\Services\R2\DeleteService as R2DeleteService;
use Modules\Storage\Services\StorageDeleteService;
use Modules\Storage\Services\StorageDriverRegistry;
use Modules\Storage\Services\StorageFlysystemFactory;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use Modules\Transfer\Models\StoredFile;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageDeleteDriversTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_r2_delete_driver_purges_cache_when_items_deleted(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $itemId = 'album/photo.jpg';

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

        $disk = $this->createMock(Filesystem::class);
        $disk->expects($this->once())->method('exists')->willReturn(true);
        $disk->expects($this->once())->method('delete')->with($account->credentials['prefix'].'/'.$itemId);

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($disk): void {
            $mock->shouldReceive('diskForAccount')->once()->andReturn($disk);
        });

        $driver = app(StorageDriverRegistry::class)->deleteDriver(StorageDriver::R2);
        $this->assertInstanceOf(R2DeleteService::class, $driver);

        $result = app(StorageDeleteService::class)->deleteMany($account, StorageDriver::R2, [$itemId]);

        $this->assertSame([$itemId], $result['deleted']);
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

        $handler = HandlerStack::create(new MockHandler([
            new Response(204),
        ]));
        $googleClient = new GoogleClient;
        $googleClient->setHttpClient(new GuzzleClient(['handler' => $handler]));
        $googleClient->setAccessToken([
            'access_token' => fake()->sha256(),
            'created' => time(),
            'expires_in' => 3600,
        ]);

        $this->mock(GoogleTokenService::class, function ($mock) use ($googleClient): void {
            $mock->shouldReceive('clientForAccount')->once()->andReturn($googleClient);
        });

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

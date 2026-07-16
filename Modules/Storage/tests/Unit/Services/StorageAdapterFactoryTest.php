<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Modules\Storage\Adapters\GoogleDriveAdapter;
use Modules\Storage\Adapters\GooglePhotosAdapter;
use Modules\Storage\Adapters\OneDriveAdapter;
use Modules\Storage\Adapters\R2Adapter;
use Modules\Storage\Contracts\StorageStreamable;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageAdapterFactory;
use Modules\Storage\Tests\TestCase;

final class StorageAdapterFactoryTest extends TestCase
{
    public function test_make_returns_google_photos_adapter(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $adapter = app(StorageAdapterFactory::class)->make($account);

        $this->assertInstanceOf(GooglePhotosAdapter::class, $adapter);
        $this->assertNotInstanceOf(StorageStreamable::class, $adapter);
    }

    public function test_make_returns_google_drive_adapter(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $adapter = app(StorageAdapterFactory::class)->make($account);

        $this->assertInstanceOf(GoogleDriveAdapter::class, $adapter);
        $this->assertInstanceOf(StorageStreamable::class, $adapter);
    }

    public function test_make_returns_onedrive_adapter(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create();
        $adapter = app(StorageAdapterFactory::class)->make($account);

        $this->assertInstanceOf(OneDriveAdapter::class, $adapter);
        $this->assertInstanceOf(StorageStreamable::class, $adapter);
    }

    public function test_make_returns_r2_adapter(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $adapter = app(StorageAdapterFactory::class)->make($account);

        $this->assertInstanceOf(R2Adapter::class, $adapter);
        $this->assertInstanceOf(StorageStreamable::class, $adapter);
    }
}

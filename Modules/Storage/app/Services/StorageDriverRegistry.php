<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Modules\Storage\Contracts\StorageBrowseDriver;
use Modules\Storage\Contracts\StorageDeleteDriver;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Services\Drivers\GoogleDriveStorageBrowseDriver;
use Modules\Storage\Services\Drivers\GoogleDriveStorageDeleteDriver;
use Modules\Storage\Services\Drivers\GooglePhotosStorageBrowseDriver;
use Modules\Storage\Services\Drivers\GooglePhotosStorageDeleteDriver;
use Modules\Storage\Services\Drivers\OneDriveStorageBrowseDriver;
use Modules\Storage\Services\Drivers\OneDriveStorageDeleteDriver;
use Modules\Storage\Services\Drivers\R2StorageBrowseDriver;
use Modules\Storage\Services\Drivers\R2StorageDeleteDriver;

final class StorageDriverRegistry
{
    /** @var array<string, StorageBrowseDriver> */
    private array $browseDrivers;

    /** @var array<string, StorageDeleteDriver> */
    private array $deleteDrivers;

    public function __construct(
        GooglePhotosStorageBrowseDriver $googlePhotosBrowse,
        GoogleDriveStorageBrowseDriver $googleDriveBrowse,
        OneDriveStorageBrowseDriver $oneDriveBrowse,
        R2StorageBrowseDriver $r2Browse,
        GooglePhotosStorageDeleteDriver $googlePhotosDelete,
        GoogleDriveStorageDeleteDriver $googleDriveDelete,
        OneDriveStorageDeleteDriver $oneDriveDelete,
        R2StorageDeleteDriver $r2Delete,
    ) {
        $this->browseDrivers = [
            StorageDriver::GooglePhotos->value => $googlePhotosBrowse,
            StorageDriver::GoogleDrive->value => $googleDriveBrowse,
            StorageDriver::OneDrive->value => $oneDriveBrowse,
            StorageDriver::R2->value => $r2Browse,
        ];

        $this->deleteDrivers = [
            StorageDriver::GooglePhotos->value => $googlePhotosDelete,
            StorageDriver::GoogleDrive->value => $googleDriveDelete,
            StorageDriver::OneDrive->value => $oneDriveDelete,
            StorageDriver::R2->value => $r2Delete,
        ];
    }

    public function browseDriver(StorageDriver $driver): StorageBrowseDriver
    {
        return $this->browseDrivers[$driver->value];
    }

    public function deleteDriver(StorageDriver $driver): StorageDeleteDriver
    {
        return $this->deleteDrivers[$driver->value];
    }
}

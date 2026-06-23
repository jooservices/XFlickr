<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Contracts\Storage\StorageBrowseDriver;
use App\Contracts\Storage\StorageDeleteDriver;
use App\Enums\StorageDriver;
use App\Services\Storage\Drivers\GoogleDriveStorageBrowseDriver;
use App\Services\Storage\Drivers\GoogleDriveStorageDeleteDriver;
use App\Services\Storage\Drivers\GooglePhotosStorageBrowseDriver;
use App\Services\Storage\Drivers\GooglePhotosStorageDeleteDriver;
use App\Services\Storage\Drivers\OneDriveStorageBrowseDriver;
use App\Services\Storage\Drivers\OneDriveStorageDeleteDriver;
use App\Services\Storage\Drivers\R2StorageBrowseDriver;
use App\Services\Storage\Drivers\R2StorageDeleteDriver;

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

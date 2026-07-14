<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use InvalidArgumentException;
use Modules\Storage\Contracts\StorageBrowseDriver;
use Modules\Storage\Contracts\StorageDeleteDriver;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Services\GoogleDrive\BrowseService as GoogleDriveBrowseService;
use Modules\Storage\Services\GoogleDrive\DeleteService as GoogleDriveDeleteService;
use Modules\Storage\Services\GooglePhotos\BrowseService as GooglePhotosBrowseService;
use Modules\Storage\Services\GooglePhotos\DeleteService as GooglePhotosDeleteService;
use Modules\Storage\Services\OneDrive\BrowseService as OneDriveBrowseService;
use Modules\Storage\Services\OneDrive\DeleteService as OneDriveDeleteService;
use Modules\Storage\Services\R2\BrowseService as R2BrowseService;
use Modules\Storage\Services\R2\DeleteService as R2DeleteService;

final class StorageDriverRegistry
{
    private const BROWSE = [
        'google_photos' => GooglePhotosBrowseService::class,
        'google' => GoogleDriveBrowseService::class,
        'onedrive' => OneDriveBrowseService::class,
        'r2' => R2BrowseService::class,
    ];

    private const DELETE = [
        'google_photos' => GooglePhotosDeleteService::class,
        'google' => GoogleDriveDeleteService::class,
        'onedrive' => OneDriveDeleteService::class,
        'r2' => R2DeleteService::class,
    ];

    public function browseDriver(StorageDriver $driver): StorageBrowseDriver
    {
        $class = self::BROWSE[$driver->value]
            ?? throw new InvalidArgumentException("No browse driver for [{$driver->value}].");

        return app($class);
    }

    public function deleteDriver(StorageDriver $driver): StorageDeleteDriver
    {
        $class = self::DELETE[$driver->value]
            ?? throw new InvalidArgumentException("No delete driver for [{$driver->value}].");

        return app($class);
    }
}

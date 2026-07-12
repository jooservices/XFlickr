<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Storage\Enums\StorageDriver;

final class StorageBrowseController
{
    public function googlePhotos(): Response
    {
        return $this->render(StorageDriver::GooglePhotos);
    }

    public function googleDrive(): Response
    {
        return $this->render(StorageDriver::GoogleDrive);
    }

    public function oneDrive(): Response
    {
        return $this->render(StorageDriver::OneDrive);
    }

    public function r2(): Response
    {
        return $this->render(StorageDriver::R2);
    }

    private function render(StorageDriver $driver): Response
    {
        return Inertia::render('Storage/Browse', [
            'provider' => $driver->value,
            'provider_slug' => $driver->routeSlug(),
            'provider_label' => $driver->label(),
            'container_label' => $driver === StorageDriver::GooglePhotos ? 'Album' : 'Folder',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Enums\StorageDriver;
use App\Repositories\StorageAccountRepository;
use App\Support\Storage\StorageAccountPresenter;
use RuntimeException;

final class StorageBrowseService
{
    public function __construct(
        private readonly GooglePhotosBrowseService $googlePhotos,
        private readonly GoogleDriveBrowseService $googleDrive,
        private readonly OneDriveBrowseService $oneDrive,
        private readonly R2BrowseService $r2,
        private readonly StorageAccountRepository $accounts,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function accountsForProvider(StorageDriver $driver): array
    {
        return $this->accounts->listForProvider($driver->value)
            ->map(fn ($account): array => StorageAccountPresenter::toPublicArray($account))
            ->all();
    }

    public function browse(
        StorageDriver $driver,
        int $accountId,
        int $perPage = 25,
        ?string $albumPageToken = null,
        ?string $itemPageToken = null,
        ?string $containerId = null,
        bool $includeAlbums = true,
        bool $includeItems = true,
    ): StorageBrowseResult {
        $account = $this->accounts->findById($accountId);

        if ($account === null || $account->provider !== $driver->value) {
            throw new RuntimeException('Storage account not found for this provider.');
        }

        $credentials = $account->credentials ?? [];

        return match ($driver) {
            StorageDriver::GooglePhotos => $this->googlePhotos->browse($credentials, $perPage, $albumPageToken, $itemPageToken, $containerId, $accountId, $includeAlbums, $includeItems),
            StorageDriver::GoogleDrive => $this->googleDrive->browse($credentials, $perPage, $albumPageToken, $itemPageToken, $containerId, $includeAlbums, $includeItems),
            StorageDriver::OneDrive => $this->oneDrive->browse($credentials, $perPage, $albumPageToken, $itemPageToken, $containerId, $includeAlbums, $includeItems),
            StorageDriver::R2 => $this->r2->browse($credentials, $perPage, $albumPageToken, $itemPageToken, $containerId, $includeAlbums, $includeItems),
        };
    }
}

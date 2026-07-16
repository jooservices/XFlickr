<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Modules\Storage\Dto\StorageBrowseResult;
use Modules\Storage\Dto\StorageListOptions;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Repositories\StorageAccountRepository;
use Modules\Storage\Support\StorageAccountPresenter;
use RuntimeException;

final class StorageBrowseService
{
    public function __construct(
        private readonly StorageAdapterFactory $factory,
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

        $adapter = $this->factory->make($account);

        return $adapter->list(new StorageListOptions(
            perPage: $perPage,
            albumPageToken: $albumPageToken,
            itemPageToken: $itemPageToken,
            containerId: $containerId,
            includeAlbums: $includeAlbums,
            includeItems: $includeItems,
        ));
    }
}

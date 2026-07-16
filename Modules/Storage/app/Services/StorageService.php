<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Storage\Contracts\StorageStreamable;
use Modules\Storage\Dto\ConnectionReport;
use Modules\Storage\Dto\StorageAppProfileDto;
use Modules\Storage\Dto\StorageDeleteOptions;
use Modules\Storage\Dto\StorageStreamResult;
use Modules\Storage\Dto\StorageUploadRequest;
use Modules\Storage\Dto\StorageUploadResult;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Events\StorageRemoteItemsRemoved;
use Modules\Storage\Exceptions\UnsupportedStorageOperation;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Repositories\StorageAccountRepository;

final class StorageService
{
    public function __construct(
        private readonly StorageSettingsService $settings,
        private readonly StorageAppProfileService $appProfiles,
        private readonly StorageAccountRepository $accounts,
        private readonly StorageAdapterFactory $factory,
        private readonly StorageBrowseLocalService $browseLocal,
        private readonly ConnectionVerificationService $verifier,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function accounts(): Collection
    {
        return $this->settings->accounts();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function apps(): Collection
    {
        return $this->settings->apps();
    }

    /**
     * @return array<string, string>
     */
    public function redirects(): array
    {
        return $this->settings->redirects();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function drivers(): Collection
    {
        return $this->settings->drivers();
    }

    public function saveAppProfile(StorageAppProfileDto $profile): string
    {
        return $this->appProfiles->save([
            'provider' => $profile->provider,
            'label' => $profile->label,
            'client_id' => $profile->clientId,
            'client_secret' => $profile->clientSecret,
            'redirect' => $profile->redirect,
        ]);
    }

    public function upload(int $storageAccountId, StorageUploadRequest $request): StorageUploadResult
    {
        $account = $this->accounts->findByIdOrFail($storageAccountId);
        $adapter = $this->factory->make($account);
        $result = $adapter->upload($request);

        $driver = StorageDriver::from($account->provider);
        if ($driver === StorageDriver::GooglePhotos) {
            $this->browseLocal->upsertItem($account, null, [
                'id' => $result->id,
                'name' => $result->name ?? basename($request->remotePath),
                'mime_type' => $result->mimeType ?? 'image/jpeg',
                'thumbnail_url' => $result->thumbnailUrl,
                'modified_at' => $result->modifiedAt ?? now()->toIso8601String(),
            ]);
        }

        return $result;
    }

    public function resolveAccountId(?int $storageAccountId = null): ?int
    {
        if ($storageAccountId !== null && $storageAccountId > 0) {
            return $this->accounts->findById($storageAccountId)?->id;
        }

        return $this->accounts->findDefault()?->id;
    }

    public function openStreamForAccount(StorageAccount $account, string $remotePath): ?StorageStreamResult
    {
        $adapter = $this->factory->make($account);

        if (! $adapter instanceof StorageStreamable) {
            throw UnsupportedStorageOperation::for($account->provider, 'stream');
        }

        return $adapter->openStream($remotePath);
    }

    /**
     * @param  list<string>  $itemIds
     * @return array{deleted: list<string>, failed: list<array{id: string, message: string}>}
     */
    public function delete(
        StorageAccount $account,
        array $itemIds,
        ?StorageDeleteOptions $options = null,
    ): array {
        $itemIds = array_values(array_filter($itemIds, static fn (string $id): bool => $id !== ''));

        if ($itemIds === []) {
            throw new InvalidArgumentException('At least one item id is required.');
        }

        $adapter = $this->factory->make($account);
        $result = $adapter->delete($itemIds, $options);

        if ($result['deleted'] === []) {
            return $result;
        }

        $driver = StorageDriver::from($account->provider);
        if ($driver === StorageDriver::GooglePhotos) {
            $this->browseLocal->deleteCachedItems($account, $result['deleted'], $options?->containerId);
        } else {
            $this->browseLocal->deleteCachedItems($account, $result['deleted']);
            event(new StorageRemoteItemsRemoved($account->id, $result['deleted']));
        }

        return $result;
    }

    public function verifyConnection(int $storageAccountId): ?ConnectionReport
    {
        return $this->verifier->verifyAccount($storageAccountId);
    }
}

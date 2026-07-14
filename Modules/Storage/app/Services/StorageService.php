<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Illuminate\Support\Collection;
use Modules\Storage\Dto\StorageAppProfileDto;

final class StorageService
{
    public function __construct(
        private readonly StorageSettingsService $settings,
        private readonly StorageAppProfileService $appProfiles,
        private readonly StorageUploadService $uploads,
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

    /**
     * @return array{id: string, path: string, etag: string|null}
     */
    public function uploadStream(int $storageAccountId, string $localPath, string $remotePath): array
    {
        return $this->uploads->uploadStream($storageAccountId, $localPath, $remotePath);
    }
}

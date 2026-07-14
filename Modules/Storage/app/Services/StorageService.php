<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Illuminate\Support\Collection;

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

    /**
     * @param  array{provider: string, label?: string|null, client_id: string, client_secret: string, redirect?: string|null}  $data
     */
    public function saveAppProfile(array $data): string
    {
        return $this->appProfiles->save($data);
    }

    /**
     * @return array{id: string, path: string, etag: string|null}
     */
    public function uploadStream(int $storageAccountId, string $localPath, string $remotePath): array
    {
        return $this->uploads->uploadStream($storageAccountId, $localPath, $remotePath);
    }
}

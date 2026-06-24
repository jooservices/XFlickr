<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Enums\StorageDriver;
use App\Models\StorageAccount;
use App\Repositories\StorageAccountRepository;
use App\Support\Storage\StorageR2Config;
use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

final class StorageUploadService
{
    public function __construct(
        private readonly GooglePhotosUploadService $googlePhotosUpload,
        private readonly StorageBrowseLocalService $browseLocal,
        private readonly StorageFlysystemFactory $flysystem,
        private readonly StorageAccountRepository $accounts,
    ) {}

    /**
     * @return array{id: string, path: string, etag: string|null}
     */
    public function uploadStream(int $storageAccountId, string $localPath, string $remotePath): array
    {
        $account = $this->accounts->findByIdOrFail($storageAccountId);
        $driver = StorageDriver::from($account->provider);
        $credentials = $account->credentials ?? [];

        if ($driver === StorageDriver::GooglePhotos) {
            $result = $this->googlePhotosUpload->uploadFile($account, $credentials, $localPath, $remotePath);

            $this->browseLocal->upsertItem($account, null, [
                'id' => $result['id'],
                'name' => $result['name'] ?? basename($remotePath),
                'mime_type' => $result['mime_type'] ?? 'image/jpeg',
                'thumbnail_url' => $result['thumbnail_url'] ?? null,
                'modified_at' => $result['modified_at'] ?? now()->toIso8601String(),
            ]);

            return [
                'id' => $result['id'],
                'path' => $result['path'],
                'etag' => $result['etag'],
            ];
        }

        $disk = $this->diskFor($account, $credentials);
        $objectKey = $this->objectKey($driver, $credentials, $remotePath);

        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException("Unable to open local file [{$localPath}].");
        }

        try {
            $disk->writeStream($objectKey, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $metadata = $this->remoteMetadata($account, $objectKey);

        return [
            'id' => $metadata['id'],
            'path' => ltrim($remotePath, '/'),
            'etag' => $metadata['etag'],
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function diskFor(StorageAccount $account, array $credentials): Filesystem
    {
        return $this->flysystem->diskForAccount($account);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function objectKey(StorageDriver $driver, array $credentials, string $remotePath): string
    {
        $remotePath = ltrim($remotePath, '/');

        if ($driver === StorageDriver::R2) {
            return StorageR2Config::from($credentials)->objectKey($remotePath);
        }

        return $remotePath;
    }

    /**
     * @return array{id: string, etag: string|null}
     */
    private function remoteMetadata(StorageAccount $account, string $objectKey): array
    {
        $driver = StorageDriver::from($account->provider);

        if ($driver === StorageDriver::R2) {
            $credentials = $account->credentials ?? [];
            $config = StorageR2Config::from($credentials);
            $client = $this->flysystem->r2Client($credentials);

            try {
                $result = $client->headObject([
                    'Bucket' => $config->bucket,
                    'Key' => $objectKey,
                ]);
            } catch (\Throwable) {
                return [
                    'id' => $config->relativePath($objectKey),
                    'etag' => null,
                ];
            }

            $etag = isset($result['ETag']) ? trim((string) $result['ETag'], '"') : null;

            return [
                'id' => $config->relativePath($objectKey),
                'etag' => $etag,
            ];
        }

        return match ($driver) {
            StorageDriver::GoogleDrive => ['id' => $objectKey, 'etag' => null],
            StorageDriver::GooglePhotos => ['id' => $objectKey, 'etag' => null],
            StorageDriver::OneDrive => ['id' => $objectKey, 'etag' => null],
        };
    }
}

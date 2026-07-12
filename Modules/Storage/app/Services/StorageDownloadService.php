<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use InvalidArgumentException;
use Modules\Storage\Contracts\StorageDownloadStreamer;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Repositories\StorageAccountRepository;
use Modules\Storage\Support\StorageR2Config;
use Modules\Storage\Support\StorageStreamResult;
use RuntimeException;

final class StorageDownloadService implements StorageDownloadStreamer
{
    public function __construct(
        private readonly StorageFlysystemFactory $flysystem,
        private readonly StorageAccountRepository $accounts,
    ) {}

    /**
     * @return array{path: string, size: int|null, etag: string|null}
     */
    public function downloadToPath(int $storageAccountId, string $remotePath, string $localPath): array
    {
        $account = $this->accounts->findByIdOrFail($storageAccountId);
        $driver = StorageDriver::from($account->provider);
        $credentials = $account->credentials ?? [];
        $objectKey = $this->objectKey($driver, $credentials, $remotePath);

        $disk = $this->flysystem->diskForAccount($account);
        $stream = $disk->readStream($objectKey);

        if ($stream === false) {
            throw new RuntimeException("Unable to read remote file [{$remotePath}].");
        }

        $directory = dirname($localPath);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory [{$directory}].");
        }

        $destination = fopen($localPath, 'wb');
        if ($destination === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw new RuntimeException("Unable to open local file [{$localPath}].");
        }

        try {
            stream_copy_to_stream($stream, $destination);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (is_resource($destination)) {
                fclose($destination);
            }
        }

        $metadata = $this->remoteMetadata($account, $objectKey);

        return [
            'path' => $localPath,
            'size' => $metadata['size'],
            'etag' => $metadata['etag'],
        ];
    }

    public function openStreamForAccount(StorageAccount $account, string $remotePath): ?StorageStreamResult
    {
        $driver = StorageDriver::from($account->provider);

        if ($driver !== StorageDriver::R2) {
            throw new InvalidArgumentException('Download is not supported for this provider yet.');
        }

        $credentials = $account->credentials ?? [];
        $objectKey = $this->objectKey($driver, $credentials, $remotePath);
        $disk = $this->flysystem->diskForAccount($account);

        if (! $disk->exists($objectKey)) {
            return null;
        }

        $stream = $disk->readStream($objectKey);
        if ($stream === false) {
            throw new RuntimeException('Unable to read remote file.');
        }

        $mimeType = $disk->mimeType($objectKey) ?: 'application/octet-stream';

        return new StorageStreamResult(
            stream: $stream,
            filename: basename($remotePath),
            mimeType: $mimeType,
        );
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
     * @return array{etag: string|null, size: int|null}
     */
    private function remoteMetadata(StorageAccount $account, string $objectKey): array
    {
        if (StorageDriver::from($account->provider) !== StorageDriver::R2) {
            return ['etag' => null, 'size' => null];
        }

        $credentials = $account->credentials ?? [];
        $config = StorageR2Config::from($credentials);
        $client = $this->flysystem->r2Client($credentials);

        try {
            $result = $client->headObject([
                'Bucket' => $config->bucket,
                'Key' => $objectKey,
            ]);
        } catch (\Throwable) {
            return ['etag' => null, 'size' => null];
        }

        $etag = isset($result['ETag']) ? trim((string) $result['ETag'], '"') : null;

        return [
            'etag' => $etag,
            'size' => isset($result['ContentLength']) ? (int) $result['ContentLength'] : null,
        ];
    }
}

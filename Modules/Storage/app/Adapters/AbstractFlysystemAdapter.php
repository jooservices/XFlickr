<?php

declare(strict_types=1);

namespace Modules\Storage\Adapters;

use Modules\Storage\Contracts\StorageAdapter;
use Modules\Storage\Contracts\StorageStreamable;
use Modules\Storage\Dto\StorageStreamResult;
use Modules\Storage\Dto\StorageUploadRequest;
use Modules\Storage\Dto\StorageUploadResult;
use Modules\Storage\Exceptions\InvalidStoragePath;
use Modules\Storage\Exceptions\StorageOperationException;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageFlysystemFactory;
use RuntimeException;
use Throwable;

abstract class AbstractFlysystemAdapter implements StorageAdapter, StorageStreamable
{
    public function __construct(
        protected readonly StorageAccount $account,
        protected readonly StorageFlysystemFactory $flysystem,
    ) {}

    final public function upload(StorageUploadRequest $request): StorageUploadResult
    {
        $relativePath = $this->normalizeRemotePath($request->remotePath);
        $objectKey = $this->objectKey($relativePath);

        if (! is_file($request->localPath) || ! is_readable($request->localPath)) {
            throw new RuntimeException('Unable to open the local upload file.');
        }

        $stream = fopen($request->localPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open the local upload file.');
        }

        try {
            $this->flysystem->diskForAccount($this->account)->writeStream($objectKey, $stream);
            $metadata = $this->uploadMetadata($objectKey);
        } catch (Throwable $exception) {
            throw StorageOperationException::fromProvider($this->account->provider, 'upload', $exception);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return new StorageUploadResult(
            id: $metadata['id'],
            path: $relativePath,
            etag: $metadata['etag'],
        );
    }

    final public function openStream(string $remotePath): ?StorageStreamResult
    {
        $relativePath = $this->normalizeRemotePath($remotePath);
        $objectKey = $this->objectKey($relativePath);

        try {
            $disk = $this->flysystem->diskForAccount($this->account);
            if (! $disk->exists($objectKey)) {
                return null;
            }

            $stream = $disk->readStream($objectKey);
            if ($stream === false) {
                throw new RuntimeException('Unable to read the remote file.');
            }

            return new StorageStreamResult(
                stream: $stream,
                filename: basename($relativePath),
                mimeType: $disk->mimeType($objectKey) ?: 'application/octet-stream',
            );
        } catch (Throwable $exception) {
            throw StorageOperationException::fromProvider($this->account->provider, 'stream', $exception);
        }
    }

    protected function objectKey(string $remotePath): string
    {
        return $remotePath;
    }

    /** @return array{id: string, etag: string|null} */
    protected function uploadMetadata(string $objectKey): array
    {
        return ['id' => $objectKey, 'etag' => null];
    }

    private function normalizeRemotePath(string $remotePath): string
    {
        if (str_contains($remotePath, "\0")) {
            throw InvalidStoragePath::for($remotePath);
        }

        $normalized = ltrim(str_replace('\\', '/', trim($remotePath)), '/');
        if ($normalized === '' || in_array('..', explode('/', $normalized), true)) {
            throw InvalidStoragePath::for($remotePath);
        }

        return $normalized;
    }
}

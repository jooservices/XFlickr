<?php

declare(strict_types=1);

namespace Modules\Storage\Support;

use RuntimeException;

final readonly class StorageR2Config
{
    public function __construct(
        public string $accessKeyId,
        public string $secretAccessKey,
        public string $bucket,
        public string $endpoint,
        public string $region,
        public string $prefix,
    ) {}

    /**
     * @param  array<string, mixed>  $credentials
     */
    public static function from(array $credentials): self
    {
        $accessKeyId = trim((string) ($credentials['access_key_id'] ?? ''));
        $secretAccessKey = trim((string) ($credentials['secret_access_key'] ?? ''));
        $bucket = trim((string) ($credentials['bucket'] ?? ''));
        $endpoint = trim((string) ($credentials['endpoint'] ?? ''));

        if ($accessKeyId === '' || $secretAccessKey === '' || $bucket === '' || $endpoint === '') {
            throw new RuntimeException('Cloudflare R2 credentials are incomplete.');
        }

        $region = trim((string) ($credentials['region'] ?? 'auto'));
        $prefix = self::normalizePrefix((string) ($credentials['prefix'] ?? ''));

        return new self(
            accessKeyId: $accessKeyId,
            secretAccessKey: $secretAccessKey,
            bucket: $bucket,
            endpoint: $endpoint,
            region: $region !== '' ? $region : 'auto',
            prefix: $prefix,
        );
    }

    public function objectKey(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');

        if ($this->prefix === '') {
            return $relativePath;
        }

        if ($relativePath === '') {
            return $this->prefix;
        }

        return $this->prefix.'/'.$relativePath;
    }

    public function relativePath(string $objectKey): string
    {
        $objectKey = ltrim($objectKey, '/');

        if ($this->prefix === '') {
            return $objectKey;
        }

        $prefix = $this->prefix.'/';
        if (str_starts_with($objectKey, $prefix)) {
            return substr($objectKey, strlen($prefix));
        }

        if ($objectKey === $this->prefix) {
            return '';
        }

        return $objectKey;
    }

    public function listPrefix(?string $containerId): string
    {
        $folder = $containerId !== null && $containerId !== ''
            ? trim($containerId, '/')
            : '';

        if ($folder === '') {
            return $this->prefix === '' ? '' : $this->prefix.'/';
        }

        return $this->objectKey($folder.'/');
    }

    private static function normalizePrefix(string $prefix): string
    {
        return trim($prefix, '/');
    }
}

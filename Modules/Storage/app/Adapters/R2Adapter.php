<?php

declare(strict_types=1);

namespace Modules\Storage\Adapters;

use Aws\S3\S3Client;
use Modules\Storage\Contracts\StorageVerifiable;
use Modules\Storage\Dto\ConnectionCheck;
use Modules\Storage\Dto\ConnectionReport;
use Modules\Storage\Dto\StorageBrowseResult;
use Modules\Storage\Dto\StorageDeleteOptions;
use Modules\Storage\Dto\StorageListOptions;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageFlysystemFactory;
use Modules\Storage\Support\StorageR2Config;
use RuntimeException;
use Throwable;

final class R2Adapter extends AbstractFlysystemAdapter implements StorageVerifiable
{
    private ?StorageR2Config $resolvedConfig = null;

    public function __construct(
        StorageAccount $account,
        StorageFlysystemFactory $flysystem,
    ) {
        parent::__construct($account, $flysystem);
    }

    private function config(): StorageR2Config
    {
        return $this->resolvedConfig ??= StorageR2Config::from($this->account->credentials ?? []);
    }

    protected function objectKey(string $remotePath): string
    {
        return $this->config()->objectKey($remotePath);
    }

    protected function uploadMetadata(string $objectKey): array
    {
        $metadata = $this->remoteMetadata($objectKey);

        return ['id' => $metadata['id'], 'etag' => $metadata['etag']];
    }

    public function delete(array $itemIds, ?StorageDeleteOptions $options = null): array
    {
        $disk = $this->flysystem->diskForAccount($this->account);

        $deleted = [];
        $failed = [];

        foreach ($itemIds as $itemId) {
            $objectKey = $this->config()->objectKey($itemId);

            try {
                if ($disk->exists($objectKey)) {
                    $disk->delete($objectKey);
                }

                $deleted[] = $itemId;
            } catch (Throwable $e) {
                $failed[] = [
                    'id' => $itemId,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }

    public function list(?StorageListOptions $options = null): StorageBrowseResult
    {
        $options ??= new StorageListOptions;
        $client = $this->flysystem->r2Client($this->account->credentials ?? []);
        $listPrefix = $this->config()->listPrefix($options->containerId);

        $albums = [];
        $albumNext = null;
        if ($options->includeAlbums) {
            [$albums, $albumNext] = $this->listFolders(
                $client,
                $listPrefix,
                $options->perPage,
                $options->albumPageToken,
            );
        }

        $items = [];
        $itemNext = null;
        if ($options->includeItems) {
            [$items, $itemNext] = $this->listFiles(
                $client,
                $listPrefix,
                $options->perPage,
                $options->itemPageToken,
            );
        }

        return new StorageBrowseResult(
            albums: $albums,
            items: $items,
            albumNextPageToken: $albumNext,
            itemNextPageToken: $itemNext,
        );
    }

    public function verify(): ConnectionReport
    {
        if ($this->account->provider !== StorageDriver::R2->value) {
            throw new RuntimeException('Account is not a Cloudflare R2 storage account.');
        }

        $credentials = $this->account->credentials ?? [];

        try {
            $config = StorageR2Config::from($credentials);
            $checks = [
                ConnectionCheck::passed('Credentials', 'R2 credentials are complete.', ["Bucket: {$config->bucket}"]),
                $this->bucketCheck($credentials, $config->bucket),
            ];
        } catch (RuntimeException $e) {
            $checks = [ConnectionCheck::failed('Credentials', $e->getMessage())];
        }

        return new ConnectionReport(
            accountId: $this->account->id,
            accountLabel: $this->account->label,
            provider: StorageDriver::R2->value,
            providerLabel: StorageDriver::R2->label(),
            checks: $checks,
        );
    }

    // ---- Browse internals ----

    /**
     * @return array{0: list<array<string, mixed>>, 1: string|null}
     */
    private function listFolders(
        S3Client $client,
        string $prefix,
        int $perPage,
        ?string $pageToken,
    ): array {
        $params = [
            'Bucket' => $this->config()->bucket,
            'Prefix' => $prefix,
            'Delimiter' => '/',
            'MaxKeys' => min($perPage, 100),
        ];

        if ($pageToken !== null && $pageToken !== '') {
            $params['ContinuationToken'] = $pageToken;
        }

        $response = $client->listObjectsV2($params);
        $albums = [];

        foreach ($response['CommonPrefixes'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $fullPrefix = (string) ($entry['Prefix'] ?? '');
            if ($fullPrefix === '') {
                continue;
            }

            $relative = rtrim($this->config()->relativePath(rtrim($fullPrefix, '/')), '/');
            $title = $relative === '' ? 'Root' : basename($relative);

            $albums[] = [
                'id' => $relative,
                'title' => $title,
                'cover_thumbnail_url' => null,
                'media_items_count' => null,
            ];
        }

        $next = isset($response['NextContinuationToken'])
            ? (string) $response['NextContinuationToken']
            : null;

        return [$albums, $next];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: string|null}
     */
    private function listFiles(
        S3Client $client,
        string $prefix,
        int $perPage,
        ?string $pageToken,
    ): array {
        $params = [
            'Bucket' => $this->config()->bucket,
            'Prefix' => $prefix,
            'MaxKeys' => min($perPage, 100),
        ];

        if ($pageToken !== null && $pageToken !== '') {
            $params['ContinuationToken'] = $pageToken;
        }

        $response = $client->listObjectsV2($params);
        $items = [];

        foreach ($response['Contents'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = (string) ($entry['Key'] ?? '');
            if ($key === '' || str_ends_with($key, '/')) {
                continue;
            }

            $relative = $this->config()->relativePath($key);
            $mimeType = $this->guessMimeType($key);

            if ($mimeType !== null && ! str_starts_with($mimeType, 'image/')) {
                continue;
            }

            $items[] = [
                'id' => $relative,
                'name' => basename($key),
                'mime_type' => $mimeType,
                'thumbnail_url' => null,
                'size' => isset($entry['Size']) ? (int) $entry['Size'] : null,
                'modified_at' => isset($entry['LastModified']) ? $entry['LastModified']->format(DATE_ATOM) : null,
                'path' => $relative,
                'web_url' => null,
            ];
        }

        $next = isset($response['NextContinuationToken'])
            ? (string) $response['NextContinuationToken']
            : null;

        return [$items, $next];
    }

    private function guessMimeType(string $key): ?string
    {
        $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'heic' => 'image/heic',
            'tif', 'tiff' => 'image/tiff',
            default => null,
        };
    }

    // ---- Verify internals ----

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function bucketCheck(array $credentials, string $bucket): ConnectionCheck
    {
        try {
            $this->flysystem->verifyR2Credentials($credentials);
        } catch (Throwable $e) {
            return ConnectionCheck::failed('Bucket access', 'Bucket probe failed: '.$e->getMessage());
        }

        return ConnectionCheck::passed('Bucket access', "Bucket \"{$bucket}\" is reachable.");
    }

    /**
     * @return array{id: string, etag: string|null, size: int|null}
     */
    private function remoteMetadata(string $objectKey): array
    {
        $credentials = $this->account->credentials ?? [];
        $client = $this->flysystem->r2Client($credentials);

        try {
            $result = $client->headObject([
                'Bucket' => $this->config()->bucket,
                'Key' => $objectKey,
            ]);
        } catch (Throwable) {
            return [
                'id' => $this->config()->relativePath($objectKey),
                'etag' => null,
                'size' => null,
            ];
        }

        $etag = isset($result['ETag']) ? trim((string) $result['ETag'], '"') : null;

        return [
            'id' => $this->config()->relativePath($objectKey),
            'etag' => $etag,
            'size' => isset($result['ContentLength']) ? (int) $result['ContentLength'] : null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Aws\S3\S3Client;
use Modules\Storage\Support\StorageR2Config;

final class R2BrowseService
{
    public function __construct(
        private readonly StorageFlysystemFactory $flysystem,
    ) {}

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function browse(
        array $credentials,
        int $perPage = 25,
        ?string $albumPageToken = null,
        ?string $itemPageToken = null,
        ?string $containerId = null,
        bool $includeAlbums = true,
        bool $includeItems = true,
    ): StorageBrowseResult {
        $config = StorageR2Config::from($credentials);
        $client = $this->flysystem->r2Client($credentials);
        $listPrefix = $config->listPrefix($containerId);

        $albums = [];
        $albumNext = null;
        if ($includeAlbums) {
            [$albums, $albumNext] = $this->listFolders(
                $client,
                $config,
                $listPrefix,
                $perPage,
                $albumPageToken,
            );
        }

        $items = [];
        $itemNext = null;
        if ($includeItems) {
            [$items, $itemNext] = $this->listFiles(
                $client,
                $config,
                $listPrefix,
                $perPage,
                $itemPageToken,
            );
        }

        return new StorageBrowseResult(
            albums: $albums,
            items: $items,
            albumNextPageToken: $albumNext,
            itemNextPageToken: $itemNext,
        );
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: string|null}
     */
    private function listFolders(
        S3Client $client,
        StorageR2Config $config,
        string $prefix,
        int $perPage,
        ?string $pageToken,
    ): array {
        $params = [
            'Bucket' => $config->bucket,
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

            $relative = rtrim($config->relativePath(rtrim($fullPrefix, '/')), '/');
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
        StorageR2Config $config,
        string $prefix,
        int $perPage,
        ?string $pageToken,
    ): array {
        $params = [
            'Bucket' => $config->bucket,
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

            $relative = $config->relativePath($key);
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
}

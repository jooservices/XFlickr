<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Support\StorageApiLogger;
use RuntimeException;

final class OneDriveBrowseService
{
    private const string GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    public function __construct(
        private readonly StorageMicrosoftTokenService $tokens,
        private readonly StorageApiLogger $apiLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function browse(
        array $credentials,
        StorageAccount $account,
        int $perPage = 25,
        ?string $albumPageToken = null,
        ?string $itemPageToken = null,
        ?string $folderId = null,
        bool $includeAlbums = true,
        bool $includeItems = true,
    ): StorageBrowseResult {
        $accessToken = $this->tokens->accessToken($credentials, $account);

        $path = $folderId !== null && $folderId !== ''
            ? "/me/drive/items/{$folderId}/children"
            : '/me/drive/root/children';

        $query = [
            '$top' => min($perPage, 100),
            '$select' => 'id,name,folder,file,size,lastModifiedDateTime,webUrl,thumbnails',
        ];

        $albums = [];
        $items = [];
        $nextAlbumToken = null;
        $nextItemToken = null;

        if ($includeAlbums) {
            if ($albumPageToken !== null && $albumPageToken !== '') {
                $folderResponse = $this->loggedGet($accessToken, $albumPageToken, $account->id);
                if (! $folderResponse->successful()) {
                    throw new RuntimeException($this->errorMessage($folderResponse));
                }
                $folderPayload = $folderResponse->json();
                foreach ($this->foldersFromPayload(is_array($folderPayload) ? $folderPayload : []) as $album) {
                    $albums[] = $album;
                }
                $nextAlbumToken = isset($folderPayload['@odata.nextLink']) ? (string) $folderPayload['@odata.nextLink'] : null;
            } elseif ($itemPageToken === null || $itemPageToken === '') {
                $folderResponse = $this->loggedGet(
                    $accessToken,
                    self::GRAPH_BASE.$path,
                    $account->id,
                    array_merge($query, [
                        '$filter' => 'folder ne null',
                    ]),
                );
                if (! $folderResponse->successful()) {
                    throw new RuntimeException($this->errorMessage($folderResponse));
                }
                $folderPayload = $folderResponse->json();
                foreach ($this->foldersFromPayload(is_array($folderPayload) ? $folderPayload : []) as $album) {
                    $albums[] = $album;
                }
                $nextAlbumToken = isset($folderPayload['@odata.nextLink']) ? (string) $folderPayload['@odata.nextLink'] : null;
            }
        }

        if ($includeItems) {
            if ($itemPageToken !== null && $itemPageToken !== '') {
                $fileResponse = $this->loggedGet($accessToken, $itemPageToken, $account->id);
            } else {
                $fileResponse = $this->loggedGet(
                    $accessToken,
                    self::GRAPH_BASE.$path,
                    $account->id,
                    array_merge($query, [
                        '$filter' => 'file ne null',
                    ]),
                );
            }

            if (! $fileResponse->successful()) {
                throw new RuntimeException($this->errorMessage($fileResponse));
            }

            $filePayload = $fileResponse->json();
            foreach ($this->filesFromPayload(is_array($filePayload) ? $filePayload : []) as $item) {
                $items[] = $item;
            }
            $nextItemToken = isset($filePayload['@odata.nextLink']) ? (string) $filePayload['@odata.nextLink'] : null;
        }

        return new StorageBrowseResult(
            albums: $albums,
            items: $items,
            albumNextPageToken: $nextAlbumToken,
            itemNextPageToken: $nextItemToken,
        );
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function loggedGet(string $accessToken, string $url, int $accountId, array $query = []): Response
    {
        $startedAt = microtime(true);
        $response = $query === []
            ? Http::withToken($accessToken)->get($url)
            : Http::withToken($accessToken)->get($url, $query);
        $this->apiLogger->logRequest(
            'onedrive',
            'GET',
            $url,
            $startedAt,
            $response,
            null,
            ['account_id' => $accountId],
        );

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function foldersFromPayload(array $payload): array
    {
        $values = is_array($payload['value'] ?? null) ? $payload['value'] : [];
        $albums = [];

        foreach ($values as $entry) {
            if (! is_array($entry) || ! isset($entry['folder'])) {
                continue;
            }

            $albums[] = [
                'id' => (string) ($entry['id'] ?? ''),
                'title' => (string) ($entry['name'] ?? 'Untitled folder'),
                'cover_thumbnail_url' => $this->thumbnailUrl($entry),
                'media_items_count' => isset($entry['folder']['childCount']) ? (int) $entry['folder']['childCount'] : null,
            ];
        }

        return $albums;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function filesFromPayload(array $payload): array
    {
        $values = is_array($payload['value'] ?? null) ? $payload['value'] : [];
        $items = [];

        foreach ($values as $entry) {
            if (! is_array($entry) || ! isset($entry['file'])) {
                continue;
            }

            $mimeType = isset($entry['file']['mimeType']) ? (string) $entry['file']['mimeType'] : '';
            if ($mimeType !== '' && ! str_starts_with($mimeType, 'image/')) {
                continue;
            }

            $items[] = [
                'id' => (string) ($entry['id'] ?? ''),
                'name' => (string) ($entry['name'] ?? 'Untitled'),
                'mime_type' => $mimeType !== '' ? $mimeType : null,
                'thumbnail_url' => $this->thumbnailUrl($entry),
                'size' => isset($entry['size']) ? (int) $entry['size'] : null,
                'modified_at' => isset($entry['lastModifiedDateTime']) ? (string) $entry['lastModifiedDateTime'] : null,
                'path' => null,
                'web_url' => isset($entry['webUrl']) ? (string) $entry['webUrl'] : null,
            ];
        }

        return $items;
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('error.message');

        return is_string($message) ? $message : 'OneDrive browse failed.';
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function thumbnailUrl(array $entry): ?string
    {
        $thumbnails = $entry['thumbnails'] ?? null;
        if (! is_array($thumbnails) || $thumbnails === []) {
            return null;
        }

        $first = $thumbnails[0] ?? null;
        if (! is_array($first)) {
            return null;
        }

        foreach (['medium', 'small', 'large'] as $size) {
            $url = $first[$size]['url'] ?? null;
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }
}

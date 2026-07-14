<?php

declare(strict_types=1);

namespace Modules\Storage\Services\GooglePhotos;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Contracts\StorageBrowseDriver;
use Modules\Storage\Dto\StorageBrowseResult;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use Modules\Storage\Support\StorageApiLogger;
use RuntimeException;

final class BrowseService implements StorageBrowseDriver
{
    private const string API_BASE = 'https://photoslibrary.googleapis.com/v1';

    public function __construct(
        private readonly GoogleTokenService $tokens,
        private readonly StorageApiLogger $apiLogger,
    ) {}

    public function browse(
        StorageAccount $account,
        int $perPage,
        ?string $albumPageToken,
        ?string $itemPageToken,
        ?string $containerId,
        bool $includeAlbums,
        bool $includeItems,
    ): StorageBrowseResult {
        $credentials = $account->credentials ?? [];
        $accessToken = $this->tokens->accessToken($credentials, $account);
        $accountId = $account->id;

        $albums = ['albums' => [], 'nextPageToken' => null];
        if ($includeAlbums && $containerId === null) {
            $albums = $this->listAlbums($accessToken, min($perPage, 50), $albumPageToken, $accountId);
        }

        $items = ['items' => [], 'nextPageToken' => null];
        if ($includeItems) {
            $items = $this->searchMediaItems(
                $accessToken,
                $perPage,
                $itemPageToken,
                $containerId,
                $accountId,
            );
        }

        return new StorageBrowseResult(
            albums: $albums['albums'],
            items: $items['items'],
            albumNextPageToken: $includeAlbums && $containerId === null ? $albums['nextPageToken'] : null,
            itemNextPageToken: $includeItems ? $items['nextPageToken'] : null,
        );
    }

    /**
     * @return array{albums: list<array<string, mixed>>, nextPageToken: string|null}
     */
    private function listAlbums(string $accessToken, int $perPage, ?string $pageToken, ?int $accountId): array
    {
        $query = ['pageSize' => $perPage];
        if ($pageToken !== null && $pageToken !== '') {
            $query['pageToken'] = $pageToken;
        }

        $endpoint = self::API_BASE.'/albums';
        $startedAt = microtime(true);
        $response = Http::withToken($accessToken)
            ->get($endpoint, $query);
        $this->apiLogger->logRequest(
            'google_photos',
            'GET',
            $endpoint,
            $startedAt,
            $response,
            null,
            ['account_id' => $accountId],
        );

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), 'Google Photos albums list failed.'));
        }

        $payload = $response->json();
        $rawAlbums = is_array($payload['albums'] ?? null) ? $payload['albums'] : [];

        $albums = [];
        foreach ($rawAlbums as $album) {
            if (! is_array($album)) {
                continue;
            }

            $coverUrl = isset($album['coverPhotoBaseUrl']) ? (string) $album['coverPhotoBaseUrl'] : null;
            $coverMediaId = $album['coverPhotoMediaItemId'] ?? null;

            $albums[] = [
                'id' => (string) ($album['id'] ?? ''),
                'title' => (string) ($album['title'] ?? 'Untitled album'),
                'cover_thumbnail_url' => is_string($coverMediaId) && $coverMediaId !== '' && $accountId !== null
                    ? '/api/v1/storage/google-photos/thumbnail?account_id='.$accountId.'&media_id='.urlencode($coverMediaId)
                    : ($coverUrl !== null ? $coverUrl.'=w128-h128' : null),
                'media_items_count' => isset($album['mediaItemsCount']) ? (int) $album['mediaItemsCount'] : null,
            ];
        }

        return [
            'albums' => $albums,
            'nextPageToken' => isset($payload['nextPageToken']) ? (string) $payload['nextPageToken'] : null,
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, nextPageToken: string|null}
     */
    private function searchMediaItems(
        string $accessToken,
        int $perPage,
        ?string $pageToken,
        ?string $albumId,
        ?int $accountId,
    ): array {
        $items = [];
        $nextPageToken = $pageToken;
        $maxEmptyPages = 15;

        for ($attempt = 0; $attempt < $maxEmptyPages; $attempt++) {
            $body = ['pageSize' => max($perPage, 25)];
            if ($nextPageToken !== null && $nextPageToken !== '') {
                $body['pageToken'] = $nextPageToken;
            }
            if ($albumId !== null && $albumId !== '') {
                $body['albumId'] = $albumId;
            }

            $endpoint = self::API_BASE.'/mediaItems:search';
            $startedAt = microtime(true);
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->post($endpoint, $body);
            $this->apiLogger->logRequest(
                'google_photos',
                'POST',
                $endpoint,
                $startedAt,
                $response,
                null,
                ['account_id' => $accountId],
            );

            if (! $response->successful()) {
                throw new RuntimeException($this->errorMessage($response->json(), 'Google Photos media search failed.'));
            }

            $payload = $response->json();
            $rawItems = is_array($payload['mediaItems'] ?? null) ? $payload['mediaItems'] : [];
            $nextPageToken = isset($payload['nextPageToken']) ? (string) $payload['nextPageToken'] : null;

            foreach ($rawItems as $mediaItem) {
                if (! is_array($mediaItem)) {
                    continue;
                }

                $baseUrl = isset($mediaItem['baseUrl']) ? (string) $mediaItem['baseUrl'] : null;
                $productUrl = isset($mediaItem['productUrl']) ? (string) $mediaItem['productUrl'] : null;
                $metadata = is_array($mediaItem['mediaMetadata'] ?? null) ? $mediaItem['mediaMetadata'] : [];
                $webUrl = $productUrl !== null && $productUrl !== ''
                    ? $productUrl
                    : ($baseUrl !== null && $baseUrl !== '' ? $baseUrl : null);

                $items[] = [
                    'id' => (string) ($mediaItem['id'] ?? ''),
                    'name' => (string) ($mediaItem['filename'] ?? 'Untitled'),
                    'mime_type' => isset($mediaItem['mimeType']) ? (string) $mediaItem['mimeType'] : null,
                    'thumbnail_url' => isset($mediaItem['id']) && $accountId !== null
                        ? '/api/v1/storage/google-photos/thumbnail?account_id='.$accountId.'&media_id='.urlencode((string) $mediaItem['id'])
                        : null,
                    'size' => null,
                    'modified_at' => isset($metadata['creationTime']) ? (string) $metadata['creationTime'] : null,
                    'path' => null,
                    'web_url' => $webUrl,
                ];

                if (count($items) >= $perPage) {
                    break 2;
                }
            }

            // Google sometimes returns an empty first page with a continuation token.
            if ($rawItems !== [] || $nextPageToken === null || $nextPageToken === '') {
                break;
            }

            if ($pageToken !== null && $pageToken !== '') {
                break;
            }
        }

        return [
            'items' => $items,
            'nextPageToken' => $nextPageToken,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function errorMessage(?array $payload, string $fallback): string
    {
        if ($payload === null) {
            return $fallback;
        }

        $message = $payload['error']['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : $fallback;
    }
}

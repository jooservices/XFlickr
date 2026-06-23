<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GooglePhotosBrowseService
{
    private const string API_BASE = 'https://photoslibrary.googleapis.com/v1';

    public function __construct(
        private readonly StorageGoogleTokenService $tokens,
    ) {}

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function browse(
        array $credentials,
        int $perPage = 25,
        ?string $albumPageToken = null,
        ?string $itemPageToken = null,
        ?string $albumId = null,
        ?int $accountId = null,
        bool $includeAlbums = true,
        bool $includeItems = true,
    ): StorageBrowseResult {
        $accessToken = $this->tokens->accessToken($credentials);

        $albums = ['albums' => [], 'nextPageToken' => null];
        if ($includeAlbums && $albumId === null) {
            $albums = $this->listAlbums($accessToken, min($perPage, 50), $albumPageToken, $accountId);
        }

        $items = ['items' => [], 'nextPageToken' => null];
        if ($includeItems) {
            $items = $this->searchMediaItems(
                $accessToken,
                $perPage,
                $itemPageToken,
                $albumId,
                $accountId,
            );
        }

        return new StorageBrowseResult(
            albums: $albums['albums'],
            items: $items['items'],
            albumNextPageToken: $includeAlbums && $albumId === null ? $albums['nextPageToken'] : null,
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

        $response = Http::withToken($accessToken)
            ->get(self::API_BASE.'/albums', $query);

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
                    ? '/api/storage/google-photos/thumbnail?account_id='.$accountId.'&media_id='.urlencode($coverMediaId)
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

            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->post(self::API_BASE.'/mediaItems:search', $body);

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
                        ? '/api/storage/google-photos/thumbnail?account_id='.$accountId.'&media_id='.urlencode((string) $mediaItem['id'])
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

<?php

declare(strict_types=1);

namespace Modules\Storage\Adapters;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Modules\Storage\Contracts\StorageAdapter;
use Modules\Storage\Contracts\StorageVerifiable;
use Modules\Storage\Dto\ConnectionCheck;
use Modules\Storage\Dto\ConnectionReport;
use Modules\Storage\Dto\StorageBrowseResult;
use Modules\Storage\Dto\StorageDeleteOptions;
use Modules\Storage\Dto\StorageListOptions;
use Modules\Storage\Dto\StorageUploadRequest;
use Modules\Storage\Dto\StorageUploadResult;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Exceptions\StorageOperationException;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\OAuthConnectionChecks;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use Modules\Storage\Support\StorageApiLogger;
use RuntimeException;
use Throwable;

final class GooglePhotosAdapter implements StorageAdapter, StorageVerifiable
{
    private const string API_BASE = 'https://photoslibrary.googleapis.com/v1';

    private const string UPLOAD_URL = 'https://photoslibrary.googleapis.com/v1/uploads';

    private const string BATCH_CREATE_URL = 'https://photoslibrary.googleapis.com/v1/mediaItems:batchCreate';

    public function __construct(
        private readonly StorageAccount $account,
        private readonly GoogleTokenService $tokens,
        private readonly OAuthConnectionChecks $oauthChecks,
        private readonly StorageApiLogger $apiLogger,
    ) {}

    public function upload(StorageUploadRequest $request): StorageUploadResult
    {
        $stream = fopen($request->localPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to read the local upload file.');
        }

        try {
            $accessToken = $this->accessToken();
            $filename = basename($request->remotePath) !== ''
                ? basename($request->remotePath)
                : basename($request->localPath);

            $uploadToken = $this->performUpload($accessToken, $stream, $filename);

            $albumId = null;
            if ($request->albumLabel !== null && $request->albumLabel !== '') {
                $albumId = $this->findOrCreateAlbum($accessToken, $request->albumLabel);
            }

            $mediaItem = $this->createMediaItem($accessToken, $filename, $uploadToken, $albumId);

            return $this->presentMediaItem($mediaItem, $filename);
        } catch (Throwable $exception) {
            throw StorageOperationException::fromProvider($this->account->provider, 'upload', $exception);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function delete(array $itemIds, ?StorageDeleteOptions $options = null): array
    {
        $containerId = $options?->containerId;

        if ($containerId === null || $containerId === '') {
            throw new InvalidArgumentException(
                'Google Photos delete requires an album. Open an album and remove items from it.',
            );
        }

        $accessToken = $this->accessToken();
        $deleted = [];
        $failed = [];

        foreach (array_chunk($itemIds, 50) as $chunk) {
            $endpoint = self::API_BASE.'/albums/'.urlencode($containerId).':batchRemoveMediaItems';
            $startedAt = microtime(true);
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->post($endpoint, [
                    'mediaItemIds' => $chunk,
                ]);
            $this->apiLogger->logRequest(
                'google_photos',
                'POST',
                $endpoint,
                $startedAt,
                $response,
                null,
                ['account_id' => $this->account->id, 'item_count' => count($chunk)],
            );

            if ($response->successful()) {
                array_push($deleted, ...$chunk);

                continue;
            }

            $message = $this->apiError($response->json(), 'Google Photos remove from album failed.');

            foreach ($chunk as $itemId) {
                $failed[] = [
                    'id' => $itemId,
                    'message' => $message,
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
        $accessToken = $this->accessToken();

        $albums = ['albums' => [], 'nextPageToken' => null];
        if ($options->includeAlbums && $options->containerId === null) {
            $albums = $this->listAlbums($accessToken, min($options->perPage, 50), $options->albumPageToken);
        }

        $items = ['items' => [], 'nextPageToken' => null];
        if ($options->includeItems) {
            $items = $this->searchMediaItems(
                $accessToken,
                $options->perPage,
                $options->itemPageToken,
                $options->containerId,
            );
        }

        return new StorageBrowseResult(
            albums: $albums['albums'],
            items: $items['items'],
            albumNextPageToken: $options->includeAlbums && $options->containerId === null ? $albums['nextPageToken'] : null,
            itemNextPageToken: $options->includeItems ? $items['nextPageToken'] : null,
        );
    }

    public function verify(): ConnectionReport
    {
        $driver = StorageDriver::GooglePhotos;
        $authorization = $this->oauthChecks->authorization($this->account);
        $checks = [
            $this->oauthChecks->oauthApp($this->account, $driver),
            $authorization,
        ];

        if ($this->oauthChecks->probeAllowed($authorization)) {
            $checks[] = $this->libraryCheck();
        }

        return new ConnectionReport(
            accountId: $this->account->id,
            accountLabel: $this->account->label,
            provider: $driver->value,
            providerLabel: $driver->label(),
            checks: $checks,
        );
    }

    // ---- Upload internals ----

    private function accessToken(): string
    {
        return $this->tokens->accessToken($this->account->credentials ?? [], $this->account);
    }

    /** @param array<string, mixed>|null $payload */
    private function apiError(?array $payload, string $fallback): string
    {
        $message = $payload['error']['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : $fallback;
    }

    /**
     * @param  resource  $stream
     */
    private function performUpload(string $accessToken, $stream, string $filename): string
    {
        $body = Utils::streamFor($stream);

        try {
            $startedAt = microtime(true);
            $uploadResponse = Http::withToken($accessToken)
                ->timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/octet-stream',
                    'X-Goog-Upload-Protocol' => 'raw',
                ])
                ->withBody($body, 'application/octet-stream')
                ->post(self::UPLOAD_URL);
            $this->apiLogger->logRequest(
                'google_photos',
                'POST',
                self::UPLOAD_URL,
                $startedAt,
                $uploadResponse,
                null,
                ['account_id' => $this->account->id, 'filename' => $filename],
            );
        } finally {
            $body->close();
        }

        if (! $uploadResponse->successful()) {
            throw new RuntimeException('Google Photos upload failed: HTTP '.$uploadResponse->status());
        }

        $uploadToken = trim($uploadResponse->body());
        if ($uploadToken === '') {
            throw new RuntimeException('Google Photos upload token was empty.');
        }

        return $uploadToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function createMediaItem(string $accessToken, string $filename, string $uploadToken, ?string $albumId = null): array
    {
        $startedAt = microtime(true);
        $body = [
            'newMediaItems' => [[
                'description' => $filename,
                'simpleMediaItem' => [
                    'fileName' => $filename,
                    'uploadToken' => $uploadToken,
                ],
            ]],
        ];

        if ($albumId !== null && $albumId !== '') {
            $body['albumId'] = $albumId;
        }

        $createResponse = Http::withToken($accessToken)
            ->timeout(60)
            ->post(self::BATCH_CREATE_URL, $body);
        $this->apiLogger->logRequest(
            'google_photos',
            'POST',
            self::BATCH_CREATE_URL,
            $startedAt,
            $createResponse,
            null,
            [
                'account_id' => $this->account->id,
                'filename' => $filename,
                'upload_token_present' => true,
            ],
        );

        if (! $createResponse->successful()) {
            throw new RuntimeException($this->apiError($createResponse->json(), 'Google Photos media item creation failed.'));
        }

        return $this->extractMediaItemFromCreateResponse($createResponse);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractMediaItemFromCreateResponse(Response $createResponse): array
    {
        $payload = $createResponse->json();
        $results = is_array($payload) ? ($payload['newMediaItemResults'][0] ?? null) : null;
        $mediaItem = is_array($results) ? ($results['mediaItem'] ?? null) : null;
        $mediaId = is_array($mediaItem) ? (string) ($mediaItem['id'] ?? '') : '';

        if ($mediaId === '') {
            $status = is_array($results) ? ($results['status'] ?? null) : null;
            $message = is_array($status) ? (string) ($status['message'] ?? 'Unknown Google Photos error.') : 'Unknown Google Photos error.';
            throw new RuntimeException($message);
        }

        return is_array($mediaItem) ? $mediaItem : [];
    }

    /**
     * @param  array<string, mixed>  $mediaItem
     */
    private function presentMediaItem(array $mediaItem, string $filename): StorageUploadResult
    {
        $mediaId = (string) ($mediaItem['id'] ?? '');
        $baseUrl = (string) ($mediaItem['baseUrl'] ?? '');
        $mimeType = (string) ($mediaItem['mimeType'] ?? 'image/jpeg');
        $creationTime = is_array($mediaItem['mediaMetadata'] ?? null)
            ? (string) ($mediaItem['mediaMetadata']['creationTime'] ?? '')
            : '';

        return new StorageUploadResult(
            id: $mediaId,
            path: $mediaId,
            etag: null,
            name: $filename,
            mimeType: $mimeType !== '' ? $mimeType : 'image/jpeg',
            thumbnailUrl: $baseUrl !== '' ? $baseUrl.'=w200-h200' : null,
            modifiedAt: $creationTime !== '' ? $creationTime : now()->toIso8601String(),
        );
    }

    // ---- Browse internals ----

    /**
     * @return array{albums: list<array<string, mixed>>, nextPageToken: string|null}
     */
    private function listAlbums(string $accessToken, int $perPage, ?string $pageToken): array
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
            ['account_id' => $this->account->id],
        );

        if (! $response->successful()) {
            throw new RuntimeException($this->apiError($response->json(), 'Google Photos albums list failed.'));
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
            $accountId = $this->account->id;

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
    ): array {
        $items = [];
        $nextPageToken = $pageToken;
        $maxEmptyPages = 15;

        for ($attempt = 0; $attempt < $maxEmptyPages; $attempt++) {
            $payload = $this->postMediaSearchPage($accessToken, $perPage, $nextPageToken, $albumId);
            $rawItems = is_array($payload['mediaItems'] ?? null) ? $payload['mediaItems'] : [];
            $nextPageToken = isset($payload['nextPageToken']) ? (string) $payload['nextPageToken'] : null;

            foreach ($rawItems as $mediaItem) {
                if (! is_array($mediaItem)) {
                    continue;
                }

                $items[] = $this->mapMediaItemToBrowseItem($mediaItem);

                if (count($items) >= $perPage) {
                    break 2;
                }
            }

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
     * @return array<string, mixed>
     */
    private function postMediaSearchPage(
        string $accessToken,
        int $perPage,
        ?string $pageToken,
        ?string $albumId,
    ): array {
        $body = ['pageSize' => max($perPage, 25)];
        if ($pageToken !== null && $pageToken !== '') {
            $body['pageToken'] = $pageToken;
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
            ['account_id' => $this->account->id],
        );

        if (! $response->successful()) {
            throw new RuntimeException($this->apiError($response->json(), 'Google Photos media search failed.'));
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $mediaItem
     * @return array<string, mixed>
     */
    private function mapMediaItemToBrowseItem(array $mediaItem): array
    {
        $baseUrl = isset($mediaItem['baseUrl']) ? (string) $mediaItem['baseUrl'] : null;
        $productUrl = isset($mediaItem['productUrl']) ? (string) $mediaItem['productUrl'] : null;
        $metadata = is_array($mediaItem['mediaMetadata'] ?? null) ? $mediaItem['mediaMetadata'] : [];
        $webUrl = $productUrl !== null && $productUrl !== ''
            ? $productUrl
            : ($baseUrl !== null && $baseUrl !== '' ? $baseUrl : null);
        $accountId = $this->account->id;

        return [
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
    }

    // ---- Verify internals ----

    private function libraryCheck(): ConnectionCheck
    {
        $sampleLimit = 10;
        $maxPages = 5;

        try {
            $accessToken = $this->accessToken();
            $albumResult = $this->collectAlbums($accessToken, $maxPages);
            $mediaResult = $this->collectMediaItems($accessToken, $maxPages);
        } catch (RuntimeException $e) {
            return ConnectionCheck::failed('Library', 'Library probe failed: '.$e->getMessage());
        }

        $suffix = $albumResult['truncated'] || $mediaResult['truncated'] ? '+' : '';
        $details = [
            'Albums:      '.count($albumResult['items']).$suffix,
            'Media items: '.count($mediaResult['items']).$suffix,
        ];

        if ($albumResult['items'] === [] && $mediaResult['items'] === []) {
            return ConnectionCheck::warning(
                'Library',
                'No app-created albums or photos visible to this OAuth Client ID. If you uploaded via JFlickr, confirm it used the same Google OAuth Client ID as XFlickr Settings.',
                $details,
            );
        }

        foreach (array_slice($albumResult['items'], 0, $sampleLimit) as $album) {
            $count = $album['media_items_count'] ?? '—';
            $details[] = "Album: {$album['title']} ({$count} items) [{$album['id']}]";
        }

        foreach (array_slice($mediaResult['items'], 0, $sampleLimit) as $item) {
            $details[] = "Photo: {$item['filename']} [{$item['id']}]";
        }

        return ConnectionCheck::passed('Library', 'App-created library content is accessible.', $details);
    }

    /**
     * @return array{items: list<array{id: string, title: string, media_items_count: int|null}>, truncated: bool}
     */
    private function collectAlbums(string $accessToken, int $maxPages): array
    {
        $albums = [];
        $pageToken = null;
        $page = 0;
        $truncated = false;

        do {
            $page++;
            $query = ['pageSize' => 50];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $endpoint = self::API_BASE.'/albums';
            $startedAt = microtime(true);
            $response = Http::withToken($accessToken)->timeout(30)->get($endpoint, $query);
            $this->apiLogger->logRequest('google_photos', 'GET', $endpoint, $startedAt, $response);
            if (! $response->successful()) {
                throw new RuntimeException($this->apiError($response->json(), 'Google Photos albums list failed.'));
            }

            $payload = $response->json();
            foreach (is_array($payload['albums'] ?? null) ? $payload['albums'] : [] as $album) {
                if (! is_array($album)) {
                    continue;
                }

                $albums[] = [
                    'id' => (string) ($album['id'] ?? ''),
                    'title' => (string) ($album['title'] ?? 'Untitled'),
                    'media_items_count' => isset($album['mediaItemsCount']) ? (int) $album['mediaItemsCount'] : null,
                ];
            }

            $pageToken = isset($payload['nextPageToken']) ? (string) $payload['nextPageToken'] : null;
            if ($page >= $maxPages && $pageToken !== null && $pageToken !== '') {
                $truncated = true;
                break;
            }
        } while ($pageToken !== null && $pageToken !== '');

        return ['items' => $albums, 'truncated' => $truncated];
    }

    /**
     * @return array{items: list<array{id: string, filename: string, mime_type: string|null, created_at: string|null}>, truncated: bool}
     */
    private function collectMediaItems(string $accessToken, int $maxPages): array
    {
        $items = [];
        $pageToken = null;
        $page = 0;
        $truncated = false;

        do {
            $page++;
            $body = ['pageSize' => 100];
            if ($pageToken !== null) {
                $body['pageToken'] = $pageToken;
            }

            $endpoint = self::API_BASE.'/mediaItems:search';
            $startedAt = microtime(true);
            $response = Http::withToken($accessToken)->timeout(30)->post($endpoint, $body);
            $this->apiLogger->logRequest('google_photos', 'POST', $endpoint, $startedAt, $response);
            if (! $response->successful()) {
                throw new RuntimeException($this->apiError($response->json(), 'Google Photos media search failed.'));
            }

            $payload = $response->json();
            foreach (is_array($payload['mediaItems'] ?? null) ? $payload['mediaItems'] : [] as $mediaItem) {
                if (! is_array($mediaItem)) {
                    continue;
                }

                $metadata = is_array($mediaItem['mediaMetadata'] ?? null) ? $mediaItem['mediaMetadata'] : [];

                $items[] = [
                    'id' => (string) ($mediaItem['id'] ?? ''),
                    'filename' => (string) ($mediaItem['filename'] ?? 'Untitled'),
                    'mime_type' => isset($mediaItem['mimeType']) ? (string) $mediaItem['mimeType'] : null,
                    'created_at' => isset($metadata['creationTime']) ? (string) $metadata['creationTime'] : null,
                ];
            }

            $pageToken = isset($payload['nextPageToken']) ? (string) $payload['nextPageToken'] : null;
            if ($page >= $maxPages && $pageToken !== null && $pageToken !== '') {
                $truncated = true;
                break;
            }
        } while ($pageToken !== null && $pageToken !== '');

        return ['items' => $items, 'truncated' => $truncated];
    }

    private function findOrCreateAlbum(string $accessToken, string $title): string
    {
        if (mb_strlen($title) > 500) {
            throw new InvalidArgumentException('Google Photos album titles may not exceed 500 characters.');
        }

        $cacheKey = 'gp_album_id:'.md5($this->account->id.':'.$title);
        $cachedId = Cache::get($cacheKey);
        if ($cachedId !== null) {
            return (string) $cachedId;
        }

        $lock = Cache::lock($cacheKey.':create', 60);
        $lock->block(10);
        try {
            $cachedId = Cache::get($cacheKey);
            if ($cachedId !== null) {
                return (string) $cachedId;
            }

            $endpoint = self::API_BASE.'/albums';
            $pageToken = null;
            do {
                $startedAt = microtime(true);
                $params = ['pageSize' => 50];
                if ($pageToken !== null) {
                    $params['pageToken'] = $pageToken;
                }
                $response = Http::withToken($accessToken)->timeout(30)->get($endpoint, $params);
                $this->apiLogger->logRequest('google_photos', 'GET', $endpoint, $startedAt, $response, null, ['account_id' => $this->account->id]);
                if (! $response->successful()) {
                    throw new RuntimeException($this->apiError($response->json(), 'Google Photos album list failed.'));
                }
                $payload = $response->json();
                foreach (is_array($payload['albums'] ?? null) ? $payload['albums'] : [] as $album) {
                    if (is_array($album) && strcasecmp((string) ($album['title'] ?? ''), $title) === 0) {
                        $albumId = (string) ($album['id'] ?? '');
                        if ($albumId !== '') {
                            Cache::put($cacheKey, $albumId, now()->addHours(24));

                            return $albumId;
                        }
                    }
                }
                $pageToken = isset($payload['nextPageToken']) ? (string) $payload['nextPageToken'] : null;
            } while ($pageToken !== null && $pageToken !== '');

            $startedAt = microtime(true);
            $createResponse = Http::withToken($accessToken)
                ->timeout(30)
                ->post($endpoint, [
                    'album' => [
                        'title' => $title,
                    ],
                ]);

            $this->apiLogger->logRequest(
                'google_photos',
                'POST',
                $endpoint,
                $startedAt,
                $createResponse,
                null,
                ['account_id' => $this->account->id, 'album_title' => $title]
            );

            if (! $createResponse->successful()) {
                throw new RuntimeException($this->apiError($createResponse->json(), "Google Photos failed to create album '{$title}'."));
            }

            $createdAlbum = $createResponse->json();
            $albumId = (string) ($createdAlbum['id'] ?? '');

            if ($albumId === '') {
                throw new RuntimeException("Google Photos album creation response was missing an ID for '{$title}'.");
            }

            Cache::put($cacheKey, $albumId, now()->addHours(24));

            return $albumId;
        } finally {
            $lock->release();
        }
    }
}

<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Illuminate\Support\Facades\Http;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Support\StorageApiLogger;
use RuntimeException;

final class GooglePhotosConnectionVerifier
{
    private const string API_BASE = 'https://photoslibrary.googleapis.com/v1';

    public function __construct(
        private readonly StorageGoogleTokenService $tokens,
        private readonly StorageAccountScopeService $scopes,
        private readonly StorageApiLogger $apiLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function verify(StorageAccount $account, int $sampleLimit = 10, int $maxPages = 5): array
    {
        if ($account->provider !== StorageDriver::GooglePhotos->value) {
            throw new RuntimeException('Account is not a Google Photos storage account.');
        }

        $credentials = $account->credentials ?? [];
        $configuredClientId = $this->configuredClientId();
        $accountClientId = (string) ($credentials['client_id'] ?? '');
        $clientIdsMatch = $configuredClientId !== '' && $accountClientId !== '' && hash_equals($configuredClientId, $accountClientId);

        $result = [
            'account' => [
                'id' => $account->id,
                'label' => $account->label,
                'connected_at' => $account->connected_at?->toIso8601String(),
            ],
            'oauth_app' => [
                'configured_client_id' => $configuredClientId !== '' ? $this->maskClientId($configuredClientId) : null,
                'account_client_id' => $accountClientId !== '' ? $this->maskClientId($accountClientId) : null,
                'client_ids_match' => $clientIdsMatch,
            ],
            'authorization' => $this->scopes->authorizationMeta($account),
            'library' => [
                'accessible' => false,
                'album_count' => 0,
                'media_item_count' => 0,
                'sample_albums' => [],
                'sample_media_items' => [],
                'truncated' => false,
                'error' => null,
            ],
        ];

        if ($this->scopes->needsReauthorization($account)) {
            $result['library']['error'] = 'Missing OAuth scopes. Reauthorize this account before browsing app-created content.';

            return $result;
        }

        try {
            $accessToken = $this->tokens->accessToken($credentials, $account);
            $albumResult = $this->collectAlbums($accessToken, $maxPages);
            $mediaResult = $this->collectMediaItems($accessToken, $maxPages);

            $result['library'] = [
                'accessible' => true,
                'album_count' => count($albumResult['items']),
                'media_item_count' => count($mediaResult['items']),
                'sample_albums' => array_slice($albumResult['items'], 0, $sampleLimit),
                'sample_media_items' => array_slice($mediaResult['items'], 0, $sampleLimit),
                'truncated' => $albumResult['truncated'] || $mediaResult['truncated'],
                'error' => null,
            ];
        } catch (RuntimeException $e) {
            $result['library']['error'] = $e->getMessage();
        }

        return $result;
    }

    private function configuredClientId(): string
    {
        $path = 'storage_app.'.StorageDriver::GooglePhotos->value;
        if (! RuntimeConfig::has($path)) {
            return '';
        }

        $value = RuntimeConfig::get($path);
        if (! is_array($value)) {
            return '';
        }

        return trim((string) ($value['client_id'] ?? $value['clientId'] ?? ''));
    }

    private function maskClientId(string $clientId): string
    {
        if (strlen($clientId) <= 12) {
            return $clientId;
        }

        return substr($clientId, 0, 8).'…'.substr($clientId, -4);
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

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function apiError(?array $payload, string $fallback): string
    {
        if ($payload === null) {
            return $fallback;
        }

        $message = $payload['error']['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : $fallback;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use App\Support\ThirdPartyApiLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JOOservices\Flickr\DTO\Common\RequestOptionsData;
use JOOservices\Flickr\Flickr;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Support\FlickrCrawlQueryParams;
use Modules\Crawler\Support\XFlickrConfig;
use Modules\Flickr\Dto\FlickrApiAuditReport;
use Modules\Flickr\Dto\FlickrTokenHealthResult;
use Throwable;

class FlickrTokenHealthService
{
    private const CACHE_SECONDS = 300;

    public function __construct(
        private readonly FlickrClientFactory $clients,
    ) {}

    public function probe(Connection $connection, bool $useCache = false): FlickrTokenHealthResult
    {
        if ($connection->disconnected_at !== null || $connection->token_payload === '') {
            return new FlickrTokenHealthResult(
                valid: false,
                errorMessage: 'Connection has no OAuth token.',
            );
        }

        if (! $useCache) {
            return $this->probeConnection($connection);
        }

        return Cache::remember(
            $this->cacheKey($connection->connection_key),
            self::CACHE_SECONDS,
            fn (): FlickrTokenHealthResult => $this->probeConnection($connection),
        );
    }

    public function forgetCache(Connection $connection): void
    {
        $this->forgetCacheForKey($connection->connection_key);
    }

    public function forgetCacheForKey(string $connectionKey): void
    {
        Cache::forget($this->cacheKey($connectionKey));
    }

    /**
     * Probe Flickr endpoints for a connection and optional contact / photo targets.
     *
     * @throws Throwable when app credentials cannot be loaded
     */
    public function auditEndpoints(
        Connection $connection,
        string $contactNsid = '',
        string $profileUrl = '',
        string $photoId = '',
    ): FlickrApiAuditReport {
        $credentials = XFlickrConfig::appCredentials($connection->app_profile ?: 'main');
        $apiKeyHint = substr($credentials->apiKey, 0, 4).'…'.substr($credentials->apiKey, -4);

        $client = $this->clients->forConnection($connection->connection_key);
        $anonymousClient = $this->clients->anonymousClient($credentials);

        $entries = [];

        $entries[] = $this->probeEntry($client, 'flickr.test.login', []);
        $entries[] = $this->probeEntry($client, 'flickr.contacts.getList', ['page' => 1, 'per_page' => 5]);
        $entries[] = $this->probeCrawlEntry(
            $client,
            'flickr.people.getPhotos',
            FlickrCrawlQueryParams::peoplePhotos($connection->connection_key, 1, 5),
        );
        $entries[] = $this->probeCrawlEntry(
            $client,
            'flickr.people.getPhotos',
            FlickrCrawlQueryParams::peoplePhotos('24662369@N07', 1, 5),
        );

        if ($profileUrl !== '') {
            $entries[] = ['type' => 'section', 'text' => "URL lookup: {$profileUrl}"];
            $lookup = $this->probeWithResponse($client, 'flickr.urls.lookupUser', ['url' => $profileUrl]);
            $entries[] = $this->toProbeEntry('flickr.urls.lookupUser', 'raw', $lookup);
            $resolvedNsid = is_string($lookup['data']['user']['id'] ?? null) ? $lookup['data']['user']['id'] : null;
            if ($resolvedNsid !== null) {
                $entries[] = ['type' => 'line', 'text' => "  → resolved NSID: {$resolvedNsid}"];
                if ($contactNsid !== '' && $contactNsid !== $resolvedNsid) {
                    $entries[] = [
                        'type' => 'warn',
                        'text' => "  Contact NSID {$contactNsid} differs from URL lookup {$resolvedNsid}",
                    ];
                }
                if ($contactNsid === '') {
                    $contactNsid = $resolvedNsid;
                }
            }
        }

        if ($contactNsid !== '') {
            $entries[] = ['type' => 'section', 'text' => "Contact probes: {$contactNsid}"];

            $info = $this->probeWithResponse($client, 'flickr.people.getInfo', ['user_id' => $contactNsid]);
            $entries[] = $this->toProbeEntry('flickr.people.getInfo', 'raw', $info);
            $photosCount = $info['data']['person']['photos']['count']['_content'] ?? null;
            $pathAlias = $info['data']['person']['path_alias'] ?? null;
            $username = $info['data']['person']['username']['_content'] ?? null;
            $entries[] = [
                'type' => 'line',
                'text' => "  getInfo · username={$username} · path_alias={$pathAlias} · photos.count={$photosCount}",
            ];

            if (is_string($pathAlias) && $pathAlias !== '') {
                $byUsername = $this->probeWithResponse($client, 'flickr.people.findByUsername', ['username' => $pathAlias]);
                $entries[] = $this->toProbeEntry('flickr.people.findByUsername', 'raw', $byUsername);
                $usernameNsid = $byUsername['data']['user']['id'] ?? $byUsername['data']['user']['nsid'] ?? null;
                if (is_string($usernameNsid) && $usernameNsid !== $contactNsid) {
                    $entries[] = [
                        'type' => 'warn',
                        'text' => "  findByUsername('{$pathAlias}') → {$usernameNsid} (differs from URL NSID; use NSID or urls.lookupUser)",
                    ];
                }
            }

            $entries[] = $this->probeCrawlEntry(
                $client,
                'flickr.people.getPhotos',
                FlickrCrawlQueryParams::peoplePhotos($contactNsid, 1, 5),
            );
            $entries[] = $this->probeEntry($client, 'flickr.people.getPhotos', array_merge(
                FlickrCrawlQueryParams::peoplePhotos($contactNsid, 1, 5),
                ['safe_search' => 1],
            ));
            $entries[] = $this->probeCrawlEntry(
                $client,
                'flickr.favorites.getList',
                FlickrCrawlQueryParams::favoritesList($contactNsid, 1, 5),
            );
            $entries[] = $this->probeCrawlEntry(
                $client,
                'flickr.photosets.getList',
                FlickrCrawlQueryParams::photosetsList($contactNsid, 1, 5),
            );
            $entries[] = $this->probeCrawlEntry(
                $client,
                'flickr.galleries.getList',
                FlickrCrawlQueryParams::galleriesList($contactNsid, 1, 5),
            );
            $entries[] = $this->probeEntry($anonymousClient, 'flickr.people.getPublicPhotos', [
                'user_id' => $contactNsid,
                'page' => 1,
                'per_page' => 5,
            ], authenticated: false);
        }

        if ($photoId !== '') {
            $entries[] = ['type' => 'section', 'text' => "Photo visibility probe: {$photoId}"];
            $signedPhoto = $this->probeWithResponse($client, 'flickr.photos.getInfo', ['photo_id' => $photoId]);
            $entries[] = $this->toProbeEntry('flickr.photos.getInfo', 'raw', $signedPhoto);
            $anonPhoto = $this->probeWithResponse(
                $anonymousClient,
                'flickr.photos.getInfo',
                ['photo_id' => $photoId],
                authenticated: false,
            );
            $entries[] = $this->toProbeEntry('flickr.photos.getInfo', 'raw', $anonPhoto);

            if ($signedPhoto['ok'] && $anonPhoto['ok']) {
                $entries[] = ['type' => 'line', 'text' => '  App key can read photo '.$photoId];
            } else {
                $entries[] = [
                    'type' => 'warn',
                    'text' => '  photos.getInfo failed for photo '.$photoId.' with the configured app key/token.',
                ];
            }
        }

        return new FlickrApiAuditReport(
            connectionKey: $connection->connection_key,
            username: $connection->username,
            appProfile: (string) $connection->app_profile,
            apiKeyHint: $apiKeyHint,
            entries: $entries,
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok: bool, ms: int, total: ?int, code: ?int, message: string, data: array<string, mixed>}
     */
    public function probeWithResponse(Flickr $client, string $method, array $params, bool $authenticated = true): array
    {
        $started = hrtime(true);

        try {
            $response = $client->raw()->call(
                $method,
                $params,
                new RequestOptionsData(authenticated: $authenticated),
            );
            $ms = (int) round((hrtime(true) - $started) / 1_000_000);
            $data = $response->data ?? [];

            return [
                'ok' => $response->ok,
                'ms' => $ms,
                'total' => $this->extractTotal($method, $data),
                'code' => $response->error?->code,
                'message' => $response->error !== null ? $response->error->message : 'unknown error',
                'data' => $data,
            ];
        } catch (Throwable $exception) {
            $ms = (int) round((hrtime(true) - $started) / 1_000_000);

            return [
                'ok' => false,
                'ms' => $ms,
                'total' => null,
                'code' => null,
                'message' => $exception->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok: bool, ms: int, total: ?int, code: ?int, message: string, data: array<string, mixed>}
     */
    public function probeCrawl(Flickr $client, string $method, array $params): array
    {
        $started = hrtime(true);

        try {
            $response = FlickrCrawlQueryParams::call($client, $method, $params);
            $ms = (int) round((hrtime(true) - $started) / 1_000_000);
            $data = $response->data ?? [];

            return [
                'ok' => $response->ok,
                'ms' => $ms,
                'total' => $this->extractTotal($method, $data),
                'code' => $response->error?->code,
                'message' => $response->error !== null ? $response->error->message : 'unknown error',
                'data' => $data,
            ];
        } catch (Throwable $exception) {
            $ms = (int) round((hrtime(true) - $started) / 1_000_000);

            return [
                'ok' => false,
                'ms' => $ms,
                'total' => null,
                'code' => null,
                'message' => $exception->getMessage(),
                'data' => [],
            ];
        }
    }

    private function probeConnection(Connection $connection): FlickrTokenHealthResult
    {
        try {
            $client = $this->clients->forConnection($connection->connection_key);
            $response = $client->raw()->call('flickr.test.login', []);

            if (! $response->ok) {
                $result = new FlickrTokenHealthResult(
                    valid: false,
                    errorCode: $response->error?->code,
                    errorMessage: $response->error?->message,
                );
                $this->logProbeFailure($connection, $result);

                return $result;
            }

            $user = $response->data['user'] ?? null;
            $userNsid = is_array($user) ? ($user['id'] ?? $user['nsid'] ?? null) : null;

            return new FlickrTokenHealthResult(
                valid: true,
                userNsid: is_string($userNsid) ? $userNsid : null,
            );
        } catch (Throwable $exception) {
            $result = new FlickrTokenHealthResult(
                valid: false,
                errorMessage: $exception->getMessage(),
            );
            $this->logProbeFailure($connection, $result);

            return $result;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{type: 'probe', method: string, mode: 'raw', ok: bool, ms: int, total: ?int, code: ?int, message: string}
     */
    private function probeEntry(Flickr $client, string $method, array $params, bool $authenticated = true): array
    {
        return $this->toProbeEntry($method, 'raw', $this->probeWithResponse($client, $method, $params, $authenticated));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{type: 'probe', method: string, mode: 'crawl', ok: bool, ms: int, total: ?int, code: ?int, message: string}
     */
    private function probeCrawlEntry(Flickr $client, string $method, array $params): array
    {
        return $this->toProbeEntry($method, 'crawl', $this->probeCrawl($client, $method, $params));
    }

    /**
     * @param  array{ok: bool, ms: int, total: ?int, code: ?int, message: string, data: array<string, mixed>}  $result
     * @return array{type: 'probe', method: string, mode: 'raw'|'crawl', ok: bool, ms: int, total: ?int, code: ?int, message: string}
     */
    private function toProbeEntry(string $method, string $mode, array $result): array
    {
        return [
            'type' => 'probe',
            'method' => $method,
            'mode' => $mode,
            'ok' => $result['ok'],
            'ms' => $result['ms'],
            'total' => $result['total'],
            'code' => $result['code'],
            'message' => $result['message'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractTotal(string $method, array $data): ?int
    {
        if ($method === 'flickr.contacts.getList') {
            return isset($data['contacts']['total']) ? (int) $data['contacts']['total'] : null;
        }

        if ($method === 'flickr.photosets.getList') {
            return isset($data['photosets']['total']) ? (int) $data['photosets']['total'] : null;
        }

        if ($method === 'flickr.galleries.getList') {
            return isset($data['galleries']['total']) ? (int) $data['galleries']['total'] : null;
        }

        if (str_contains($method, 'getPhotos') || str_contains($method, 'favorites')) {
            return isset($data['photos']['total']) ? (int) $data['photos']['total'] : null;
        }

        return null;
    }

    private function logProbeFailure(Connection $connection, FlickrTokenHealthResult $result): void
    {
        Log::warning('Flickr token health probe failed.', [
            'connection_key_fp' => ThirdPartyApiLogger::fingerprint($connection->connection_key),
            'error_code' => $result->errorCode,
            'error' => $result->errorMessage,
        ]);
    }

    private function cacheKey(string $connectionKey): string
    {
        return 'flickr_token_health:'.$connectionKey;
    }
}

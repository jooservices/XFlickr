<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use App\Support\ThirdPartyApiLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Services\FlickrClientFactory;
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

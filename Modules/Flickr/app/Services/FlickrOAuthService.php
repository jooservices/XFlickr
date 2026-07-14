<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use App\Support\ThirdPartyApiLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use JOOservices\Flickr\Config\FlickrConfig;
use JOOservices\Flickr\Contracts\Client\FlickrTransportContract;
use JOOservices\Flickr\DTO\Auth\AccessTokenData;
use JOOservices\Flickr\Enums\AuthPermission;
use JOOservices\Flickr\Exceptions\AuthenticationException;
use JOOservices\Flickr\Flickr;
use JOOservices\Flickr\FlickrFactory;
use Modules\Crawler\Facades\FlickrService;
use Modules\Crawler\Models\Connection;
use Modules\Flickr\Events\FlickrAccountConnected;
use Modules\Flickr\Events\FlickrAccountDisconnected;
use Modules\Flickr\Support\ConnectionPresenter;
use Throwable;

final class FlickrOAuthService
{
    public function __construct(
        private readonly FlickrAppProfileService $appProfiles,
        private readonly FlickrTokenHealthService $tokenHealth,
        private readonly ?FlickrTransportContract $transport = null,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array{url: string, oauth_token: string, oauth_token_secret: string, app_profile: string}
     */
    public function begin(?string $appProfile = null, array $context = []): array
    {
        $profile = $appProfile ?? 'main';
        $startedAt = microtime(true);

        try {
            $client = $this->clientForProfile($profile);

            $requestToken = $client->auth()->requestToken(AuthPermission::Read);
            $url = $client->auth()->authorizationUrl($requestToken, AuthPermission::Read);

            $payload = [
                'url' => $url,
                'oauth_token' => $requestToken->oauthToken,
                'oauth_token_secret' => $requestToken->oauthTokenSecret,
                'app_profile' => $profile,
            ];

            Log::info('Flickr OAuth begin succeeded.', $this->logContext($startedAt, $profile, [
                'oauth_token_fp' => ThirdPartyApiLogger::fingerprint($requestToken->oauthToken),
            ], $context));

            return $payload;
        } catch (Throwable $exception) {
            Log::warning('Flickr OAuth begin failed.', $this->logContext($startedAt, $profile, [
                'error' => $exception->getMessage(),
            ], $context));

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function complete(
        string $oauthToken,
        string $oauthVerifier,
        string $oauthTokenSecret,
        string $appProfile = 'main',
        array $context = [],
    ): Connection {
        $startedAt = microtime(true);

        try {
            $client = $this->clientForProfile($appProfile);

            $accessToken = $client->auth()->accessToken($oauthToken, $oauthVerifier, $oauthTokenSecret);
            $nsid = $accessToken->userNsid ?? 'unknown';

            $connection = FlickrService::connections()->register(
                connectionKey: $nsid,
                tokenPayload: $this->tokenPayloadFromAccessToken($accessToken),
                appProfile: $appProfile,
                username: $accessToken->username,
                fullname: $accessToken->fullname,
                activate: true,
            );

            if ($connection->connection_key === 'unknown' && $accessToken->userNsid !== null) {
                FlickrService::connections()->disconnect('unknown');

                $connection = FlickrService::connections()->register(
                    connectionKey: $accessToken->userNsid,
                    tokenPayload: $this->tokenPayloadFromAccessToken($accessToken),
                    appProfile: $appProfile,
                    username: $accessToken->username,
                    fullname: $accessToken->fullname,
                    activate: true,
                );
            }

            $connection = $connection->fresh() ?? $connection;
            $this->assertTokenHealthyAfterConnect($connection, $appProfile);

            $this->tokenHealth->forgetCache($connection);

            event(new FlickrAccountConnected(
                connectionKey: $connection->connection_key,
                appProfile: $appProfile,
                username: $accessToken->username,
            ));

            Log::info('Flickr OAuth complete succeeded.', $this->logContext($startedAt, $appProfile, [
                'oauth_token_fp' => ThirdPartyApiLogger::fingerprint($oauthToken),
                'connection_key_fp' => ThirdPartyApiLogger::fingerprint($connection->connection_key),
            ], $context));

            return $connection;
        } catch (Throwable $exception) {
            Log::warning('Flickr OAuth complete failed.', $this->logContext($startedAt, $appProfile, [
                'error' => $exception->getMessage(),
                'oauth_token_fp' => ThirdPartyApiLogger::fingerprint($oauthToken),
            ], $context));

            throw $exception;
        }
    }

    public function disconnect(string $connectionKey): void
    {
        if ($connectionKey === '') {
            return;
        }

        FlickrService::connections()->disconnect($connectionKey);
        $this->tokenHealth->forgetCacheForKey($connectionKey);

        event(new FlickrAccountDisconnected(connectionKey: $connectionKey));
    }

    public function activate(string $connectionKey): void
    {
        if ($connectionKey === '') {
            return;
        }

        FlickrService::connections()->activate($connectionKey);
    }

    /**
     * @return array{connected: bool, account: array<string, mixed>|null}
     */
    public function status(): array
    {
        $connection = $this->activeConnection();
        if ($connection === null) {
            return ['connected' => false, 'account' => null];
        }

        return [
            'connected' => true,
            'account' => $this->connectionToArray($connection),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listAccounts(): Collection
    {
        return $this->listConnections()
            ->map(fn (Connection $connection): array => $this->connectionToArray($connection));
    }

    /**
     * @return Collection<int, Connection>
     */
    public function listConnections(): Collection
    {
        return FlickrService::connections()->list();
    }

    public function activeConnection(): ?Connection
    {
        return FlickrService::connections()->active();
    }

    /**
     * Pre-connection OAuth: no stored connection yet — approved direct FlickrFactory use.
     */
    private function clientForProfile(string $profile): Flickr
    {
        return FlickrFactory::make(
            FlickrConfig::from($this->appProfiles->flickrClientConfig($profile)),
            transport: $this->transport,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionToArray(Connection $connection): array
    {
        $decoded = json_decode($connection->token_payload, true);
        $hasTokens = is_array($decoded)
            && ! empty($decoded['oauthToken'])
            && ! empty($decoded['oauthTokenSecret']);
        $isConnected = $connection->disconnected_at === null && $hasTokens;

        return [
            ...ConnectionPresenter::toArray($connection),
            'is_connected' => $isConnected,
            'token_valid' => null,
        ];
    }

    private function assertTokenHealthyAfterConnect(Connection $connection, string $appProfile): void
    {
        $health = $this->tokenHealth->probe($connection);

        if ($health->valid) {
            return;
        }

        FlickrService::connections()->disconnect($connection->connection_key);

        throw new AuthenticationException(
            'Reconnect failed: stored token cannot call Flickr API. '
            ."Verify API key and secret for profile [{$appProfile}] match your Flickr app."
            .($health->errorMessage !== null ? ' ('.$health->errorMessage.')' : ''),
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function tokenPayloadFromAccessToken(AccessTokenData $accessToken): array
    {
        return [
            'oauthToken' => $accessToken->oauthToken,
            'oauthTokenSecret' => $accessToken->oauthTokenSecret,
            'userNsid' => $accessToken->userNsid,
            'username' => $accessToken->username,
            'fullname' => $accessToken->fullname,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function logContext(float $startedAt, string $appProfile, array $extra = [], array $context = []): array
    {
        $logger = new ThirdPartyApiLogger;

        return [
            'provider' => 'flickr',
            'app_profile' => $appProfile,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ...$logger->safeContext($extra),
            ...$logger->safeContext($context),
        ];
    }
}

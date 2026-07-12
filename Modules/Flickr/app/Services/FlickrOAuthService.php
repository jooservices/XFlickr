<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use Illuminate\Support\Collection;
use JOOservices\Flickr\Config\FlickrConfig;
use JOOservices\Flickr\DTO\Auth\AccessTokenData;
use JOOservices\Flickr\Enums\AuthPermission;
use JOOservices\Flickr\Exceptions\AuthenticationException;
use JOOservices\Flickr\Flickr;
use JOOservices\Flickr\FlickrFactory;
use JOOservices\XFlickrCrawler\Facades\FlickrService;
use JOOservices\XFlickrCrawler\Models\Connection;
use Modules\Flickr\Events\FlickrAccountConnected;
use Modules\Flickr\Events\FlickrAccountDisconnected;
use Modules\Flickr\Support\ConnectionPresenter;

final class FlickrOAuthService
{
    public function __construct(
        private readonly FlickrAppProfileService $appProfiles,
        private readonly FlickrTokenHealthService $tokenHealth,
    ) {}

    /**
     * @return array{url: string, oauth_token: string, oauth_token_secret: string, app_profile: string}
     */
    public function begin(?string $appProfile = null): array
    {
        $profile = $appProfile ?? 'main';
        $client = $this->clientForProfile($profile);

        $requestToken = $client->auth()->requestToken(AuthPermission::Read);
        $url = $client->auth()->authorizationUrl($requestToken, AuthPermission::Read);

        return [
            'url' => $url,
            'oauth_token' => $requestToken->oauthToken,
            'oauth_token_secret' => $requestToken->oauthTokenSecret,
            'app_profile' => $profile,
        ];
    }

    public function complete(
        string $oauthToken,
        string $oauthVerifier,
        string $oauthTokenSecret,
        string $appProfile = 'main',
    ): Connection {
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

        return $connection;
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
        return FlickrService::connections()
            ->list()
            ->map(fn (Connection $connection): array => $this->connectionToArray($connection));
    }

    public function activeConnection(): ?Connection
    {
        return FlickrService::connections()->active();
    }

    private function clientForProfile(string $profile): Flickr
    {
        return FlickrFactory::make(
            FlickrConfig::from($this->appProfiles->flickrClientConfig($profile)),
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
}

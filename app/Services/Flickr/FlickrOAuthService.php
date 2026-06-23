<?php

declare(strict_types=1);

namespace App\Services\Flickr;

use App\Support\Flickr\ConnectionPresenter;
use Illuminate\Support\Collection;
use JOOservices\Flickr\Config\FlickrConfig;
use JOOservices\Flickr\DTO\Auth\AccessTokenData;
use JOOservices\Flickr\Enums\AuthPermission;
use JOOservices\Flickr\Flickr;
use JOOservices\Flickr\FlickrFactory;
use JOOservices\XFlickrCrawler\Facades\FlickrService;
use JOOservices\XFlickrCrawler\Models\Connection;

final class FlickrOAuthService
{
    public function __construct(
        private readonly FlickrAppProfileService $appProfiles,
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

            return FlickrService::connections()->register(
                connectionKey: $accessToken->userNsid,
                tokenPayload: $this->tokenPayloadFromAccessToken($accessToken),
                appProfile: $appProfile,
                username: $accessToken->username,
                fullname: $accessToken->fullname,
                activate: true,
            );
        }

        return $connection;
    }

    public function disconnect(string $connectionKey): void
    {
        if ($connectionKey === '') {
            return;
        }

        FlickrService::connections()->disconnect($connectionKey);
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

        return [
            ...ConnectionPresenter::toArray($connection),
            'is_connected' => $connection->disconnected_at === null && $hasTokens,
        ];
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

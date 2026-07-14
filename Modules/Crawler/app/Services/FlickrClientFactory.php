<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use JOOservices\Flickr\Auth\InMemoryTokenStore;
use JOOservices\Flickr\Config\FlickrConfig;
use JOOservices\Flickr\Contracts\Client\FlickrTransportContract;
use JOOservices\Flickr\DTO\Auth\AccessTokenData;
use JOOservices\Flickr\Flickr;
use JOOservices\Flickr\FlickrFactory;
use Modules\Crawler\DTO\FlickrAppCredentialsDto;
use Modules\Crawler\DTO\FlickrTokenPayload;
use Modules\Crawler\Repositories\ConnectionRepository;
use Modules\Crawler\Support\ForceAuthenticatedFlickr;
use Modules\Crawler\Support\XFlickrConfig;
use RuntimeException;

final class FlickrClientFactory
{
    public function __construct(
        private readonly ?FlickrTransportContract $transport = null,
        private readonly ?ConnectionRepository $connections = null,
    ) {}

    public function forConnection(string $connectionKey): Flickr
    {
        $connection = $this->connectionRepository()->findByKey($connectionKey);

        if ($connection === null) {
            throw new RuntimeException("Flickr connection [{$connectionKey}] was not found.");
        }

        $credentials = XFlickrConfig::appCredentials($connection->app_profile);

        return $this->makeClient($credentials, $connection->token_payload);
    }

    public function makeClient(FlickrAppCredentialsDto $credentials, string $tokenPayload): Flickr
    {
        $token = $this->parseTokenPayload($tokenPayload);
        $config = FlickrConfig::from([
            'apiKey' => $credentials->apiKey,
            'apiSecret' => $credentials->apiSecret,
        ]);

        return ForceAuthenticatedFlickr::wrap(FlickrFactory::make(
            $config,
            tokenStore: new InMemoryTokenStore($token),
            transport: $this->transport,
        ));
    }

    public function transport(): ?FlickrTransportContract
    {
        return $this->transport;
    }

    private function parseTokenPayload(string $tokenPayload): AccessTokenData
    {
        $payload = FlickrTokenPayload::fromJson($tokenPayload);

        return AccessTokenData::from([
            'oauthToken' => $payload->oauthToken,
            'oauthTokenSecret' => $payload->oauthTokenSecret,
            'userNsid' => $payload->userNsid,
            'username' => $payload->username,
            'fullname' => $payload->fullname,
        ]);
    }

    private function connectionRepository(): ConnectionRepository
    {
        return $this->connections ?? app(ConnectionRepository::class);
    }
}

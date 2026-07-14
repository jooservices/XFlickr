<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Client;

use JOOservices\Dto\Exceptions\HydrationException;
use JOOservices\Flickr\Flickr;
use JOOservices\Flickr\Services\RawApiService;
use Modules\Crawler\Client\ForceAuthenticatedFlickrClient;
use Modules\Crawler\DTO\FlickrAppCredentialsDto;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Tests\TestCase;
use ReflectionProperty;
use RuntimeException;

final class FlickrClientFactoryMakeClientTest extends TestCase
{
    public function test_make_client_with_valid_token(): void
    {
        $factory = new FlickrClientFactory;
        $client = $factory->makeClient(
            new FlickrAppCredentialsDto(apiKey: 'k', apiSecret: 's'),
            $this->sampleToken(),
        );

        $this->assertInstanceOf(Flickr::class, $client);
    }

    public function test_make_client_authenticated_true_wraps_force_auth_client(): void
    {
        $factory = new FlickrClientFactory;
        $client = $factory->makeClient(
            new FlickrAppCredentialsDto(apiKey: 'k', apiSecret: 's'),
            $this->sampleToken(),
            authenticated: true,
        );

        $this->assertInstanceOf(ForceAuthenticatedFlickrClient::class, $this->rawInnerClient($client));
    }

    public function test_make_client_authenticated_false_does_not_wrap_force_auth_client(): void
    {
        $factory = new FlickrClientFactory;
        $client = $factory->makeClient(
            new FlickrAppCredentialsDto(apiKey: 'k', apiSecret: 's'),
            $this->sampleToken(),
            authenticated: false,
        );

        $this->assertNotInstanceOf(ForceAuthenticatedFlickrClient::class, $this->rawInnerClient($client));
    }

    public function test_anonymous_client_returns_flickr_without_token_payload(): void
    {
        $factory = new FlickrClientFactory;
        $client = $factory->anonymousClient(
            new FlickrAppCredentialsDto(apiKey: 'k', apiSecret: 's'),
        );

        $this->assertInstanceOf(Flickr::class, $client);
        $this->assertNotInstanceOf(ForceAuthenticatedFlickrClient::class, $this->rawInnerClient($client));
    }

    public function test_make_client_rejects_invalid_json(): void
    {
        $factory = new FlickrClientFactory;

        $this->expectException(HydrationException::class);
        $factory->makeClient(
            new FlickrAppCredentialsDto(apiKey: 'k', apiSecret: 's'),
            'not-json',
        );
    }

    public function test_for_connection_throws_when_row_missing(): void
    {
        $factory = new FlickrClientFactory;

        $this->expectException(RuntimeException::class);
        $factory->forConnection('missing-connection');
    }

    private function rawInnerClient(Flickr $client): object
    {
        $raw = $client->raw();
        $this->assertInstanceOf(RawApiService::class, $raw);

        $property = new ReflectionProperty(RawApiService::class, 'client');

        return $property->getValue($raw);
    }
}

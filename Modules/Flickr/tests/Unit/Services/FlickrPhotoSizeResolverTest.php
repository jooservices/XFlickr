<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services;

use JOOservices\Flickr\Client\FakeFlickrTransport;
use Modules\Crawler\DTO\FlickrAppCredentialsDto;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Flickr\Services\FlickrPhotoSizeResolver;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;

final class FlickrPhotoSizeResolverTest extends TestCase
{
    use CreatesFlickrConnection;

    public function test_it_uses_cached_get_sizes_without_calling_flickr_api(): void
    {
        $connection = $this->createFlickrConnection();

        Photo::query()->create([
            'flickr_photo_id' => 'photo-1',
            'owner_nsid' => 'friend@N01',
            'title' => 'Test',
            'secret' => 'abc123',
            'server' => '65535',
            'raw_payload' => [
                'sizes' => [
                    ['label' => 'Original', 'source' => 'https://example.test/original.jpg'],
                ],
            ],
        ]);

        $download = app(FlickrPhotoSizeResolver::class)->resolve('photo-1', $connection);

        $this->assertSame('https://example.test/original.jpg', $download->url);
        $this->assertSame('original', $download->variant);
    }

    public function test_connection_client_oauth_signs_registry_public_get_sizes(): void
    {
        $transport = FakeFlickrTransport::new()->pushJson([
            'stat' => 'ok',
            'sizes' => [
                'size' => [
                    [
                        'label' => 'Original',
                        'source' => 'https://example.test/original.jpg',
                        'width' => 100,
                        'height' => 100,
                    ],
                ],
            ],
        ]);

        $factory = new FlickrClientFactory($transport);
        $client = $factory->makeClient(
            new FlickrAppCredentialsDto(apiKey: 'key', apiSecret: 'secret'),
            json_encode([
                'oauthToken' => 'access-token',
                'oauthTokenSecret' => 'access-secret',
                'userNsid' => '94529704@N02',
            ], JSON_THROW_ON_ERROR),
        );

        $response = $client->photos()->getSizes('52957706290');

        $this->assertTrue($response->ok);
        $request = $transport->lastRequest();
        $params = $request['options']['query'] ?? $request['options']['form_params'] ?? [];
        $this->assertSame('flickr.photos.getSizes', $params['method'] ?? null);
        $this->assertSame('52957706290', $params['photo_id'] ?? null);
        $this->assertArrayHasKey('oauth_token', $params);
        $this->assertSame('access-token', $params['oauth_token']);
    }

    public function test_it_throws_when_photo_is_missing_from_catalog(): void
    {
        $connection = $this->createFlickrConnection();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('was not found in catalog');

        app(FlickrPhotoSizeResolver::class)->resolve('missing-photo', $connection);
    }

    public function test_it_fetches_sizes_from_flickr_and_persists_payload(): void
    {
        $connection = $this->createFlickrConnection();

        Photo::query()->create([
            'flickr_photo_id' => 'photo-fetch',
            'owner_nsid' => 'friend@N01',
            'title' => 'Fetch me',
            'secret' => 'abc123',
            'server' => '65535',
            'raw_payload' => [],
        ]);

        $transport = FakeFlickrTransport::new()->pushJson([
            'stat' => 'ok',
            'sizes' => [
                'size' => [
                    ['label' => 'Large', 'source' => 'https://example.test/large.jpg'],
                ],
            ],
        ]);
        $this->app->instance(FlickrClientFactory::class, new FlickrClientFactory($transport));

        $download = app(FlickrPhotoSizeResolver::class)->resolve('photo-fetch', $connection);

        $this->assertSame('https://example.test/large.jpg', $download->url);
        $this->assertSame('large', $download->variant);

        $photo = Photo::query()->where('flickr_photo_id', 'photo-fetch')->firstOrFail();
        $this->assertIsArray($photo->raw_payload);
        $this->assertArrayHasKey('sizes', $photo->raw_payload);
        $this->assertArrayHasKey('sizes_fetched_at', $photo->raw_payload);
    }

    public function test_it_throws_when_flickr_get_sizes_fails(): void
    {
        $connection = $this->createFlickrConnection();

        Photo::query()->create([
            'flickr_photo_id' => 'photo-fail',
            'owner_nsid' => 'friend@N01',
            'title' => 'Fail me',
            'secret' => 'abc123',
            'server' => '65535',
            'raw_payload' => [],
        ]);

        $transport = FakeFlickrTransport::new()->pushJson([
            'stat' => 'fail',
            'code' => 1,
            'message' => 'Photo not found',
        ]);
        $this->app->instance(FlickrClientFactory::class, new FlickrClientFactory($transport));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('flickr.photos.getSizes failed');

        app(FlickrPhotoSizeResolver::class)->resolve('photo-fail', $connection);
    }

    public function test_it_throws_when_flickr_returns_no_download_candidates(): void
    {
        $connection = $this->createFlickrConnection();

        Photo::query()->create([
            'flickr_photo_id' => 'photo-empty',
            'owner_nsid' => 'friend@N01',
            'title' => 'Empty',
            'secret' => 'abc123',
            'server' => '65535',
            'raw_payload' => [],
        ]);

        $transport = FakeFlickrTransport::new()->pushJson([
            'stat' => 'ok',
            'sizes' => ['size' => []],
        ]);
        $this->app->instance(FlickrClientFactory::class, new FlickrClientFactory($transport));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No downloadable size returned');

        app(FlickrPhotoSizeResolver::class)->resolve('photo-empty', $connection);
    }
}

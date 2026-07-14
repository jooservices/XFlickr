<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests;

use Illuminate\Support\Facades\Redis;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use JOOservices\Flickr\Contracts\Client\FlickrTransportContract;
use JOOservices\LaravelConfig\Contracts\ConfigStore;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Services\FlickrClientFactory;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase as HostTestCase;

abstract class TestCase extends HostTestCase
{
    use SafeRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->bound('config-store') && ! app()->bound(ConfigStore::class)) {
            $this->markTestSkipped('Host laravel-config store is required for Flickr module tests.');
        }

        $this->seedFlickrAppCredentials();
    }

    protected function seedFlickrAppCredentials(string $profile = 'main'): void
    {
        foreach (array_keys(RuntimeConfig::group('xflickr_app')) as $existingProfile) {
            RuntimeConfig::forget("xflickr_app.{$existingProfile}");
        }

        RuntimeConfig::set("xflickr_app.{$profile}", [
            'apiKey' => 'test-api-key-12345',
            'apiSecret' => 'test-api-secret',
            'callbackUrl' => 'http://localhost/flickr/callback',
        ], 'json');
        RuntimeConfig::refresh();
    }

    protected function loadModuleFixture(string $relativePath): string
    {
        $path = __DIR__.'/Fixtures/'.$relativePath;
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Fixture not found: {$relativePath}");
        }

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadJsonFixture(string $relativePath): array
    {
        $decoded = json_decode($this->loadModuleFixture($relativePath), true);
        if (! is_array($decoded)) {
            throw new \RuntimeException("Fixture must decode to array: {$relativePath}");
        }

        return $decoded;
    }

    protected function bindFakeFlickrTransport(FakeFlickrTransport $transport): FakeFlickrTransport
    {
        $this->bindFlickrTransport($transport);

        return $transport;
    }

    protected function bindFlickrTransport(FlickrTransportContract $transport): void
    {
        $this->app->instance(FlickrClientFactory::class, new FlickrClientFactory($transport));
    }

    protected function requiresRedis(): void
    {
        try {
            Redis::connection()->ping();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Redis is required for this test: '.$exception->getMessage());
        }
    }

    protected function cleanLimiterKeys(string ...$connectionKeys): void
    {
        foreach ($connectionKeys as $connectionKey) {
            Redis::del(
                "xflickr:req:{$connectionKey}:window",
                "xflickr:req:{$connectionKey}:last",
                "xflickr:pause:{$connectionKey}",
            );
        }
    }

    protected function sampleTokenPayload(): string
    {
        return json_encode([
            'oauthToken' => 'access-token',
            'oauthTokenSecret' => 'access-secret',
            'userNsid' => '12037949629@N01',
        ], JSON_THROW_ON_ERROR);
    }
}

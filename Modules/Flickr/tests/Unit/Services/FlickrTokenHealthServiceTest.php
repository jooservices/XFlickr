<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use Modules\Crawler\Models\Connection;
use Modules\Flickr\Services\FlickrTokenHealthService;
use Modules\Flickr\Tests\TestCase;

final class FlickrTokenHealthServiceTest extends TestCase
{
    public function test_probe_returns_invalid_when_connection_has_no_token(): void
    {
        $connection = new Connection([
            'connection_key' => '94529704@N02',
            'token_payload' => '',
            'disconnected_at' => null,
        ]);

        $result = app(FlickrTokenHealthService::class)->probe($connection);

        $this->assertFalse($result->valid);
        $this->assertSame('Connection has no OAuth token.', $result->errorMessage);
    }

    public function test_probe_returns_invalid_when_connection_is_disconnected(): void
    {
        $connection = new Connection([
            'connection_key' => '94529704@N02',
            'token_payload' => $this->sampleTokenPayload(),
            'disconnected_at' => now(),
        ]);

        $result = app(FlickrTokenHealthService::class)->probe($connection);

        $this->assertFalse($result->valid);
        $this->assertSame('Connection has no OAuth token.', $result->errorMessage);
    }

    public function test_probe_returns_valid_when_flickr_test_login_succeeds(): void
    {
        Connection::query()->create([
            'connection_key' => '94529704@N02',
            'app_profile' => 'main',
            'token_payload' => $this->sampleTokenPayload(),
        ]);

        $this->bindFakeFlickrTransport(
            FakeFlickrTransport::new()->pushJson([
                'stat' => 'ok',
                'user' => ['id' => '94529704@N02', 'username' => 'healthy-user'],
            ]),
        );

        $connection = Connection::query()->where('connection_key', '94529704@N02')->firstOrFail();
        $result = app(FlickrTokenHealthService::class)->probe($connection);

        $this->assertTrue($result->valid);
        $this->assertSame('94529704@N02', $result->userNsid);
    }

    public function test_probe_returns_api_error_details_when_login_fails(): void
    {
        Connection::query()->create([
            'connection_key' => '94529704@N02',
            'app_profile' => 'main',
            'token_payload' => $this->sampleTokenPayload(),
        ]);

        $this->bindFakeFlickrTransport(
            FakeFlickrTransport::new()->pushJson([
                'stat' => 'fail',
                'code' => 99,
                'message' => 'Invalid auth token',
            ]),
        );

        $connection = Connection::query()->where('connection_key', '94529704@N02')->firstOrFail();
        $result = app(FlickrTokenHealthService::class)->probe($connection);

        $this->assertFalse($result->valid);
        $this->assertSame(99, $result->errorCode);
        $this->assertSame('Invalid auth token', $result->errorMessage);
    }

    public function test_probe_returns_exception_message_when_client_factory_fails(): void
    {
        $connection = new Connection([
            'connection_key' => 'missing@N01',
            'token_payload' => $this->sampleTokenPayload(),
            'disconnected_at' => null,
        ]);

        $result = app(FlickrTokenHealthService::class)->probe($connection);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('was not found', (string) $result->errorMessage);
    }

    public function test_probe_can_use_cache_and_forget_cache(): void
    {
        Connection::query()->create([
            'connection_key' => '94529704@N02',
            'app_profile' => 'main',
            'token_payload' => $this->sampleTokenPayload(),
        ]);

        $transport = FakeFlickrTransport::new()->pushJson([
            'stat' => 'ok',
            'user' => ['id' => '94529704@N02'],
        ]);
        $this->bindFakeFlickrTransport($transport);

        $connection = Connection::query()->where('connection_key', '94529704@N02')->firstOrFail();
        $service = app(FlickrTokenHealthService::class);

        $first = $service->probe($connection, useCache: true);
        $second = $service->probe($connection, useCache: true);

        $this->assertTrue($first->valid);
        $this->assertTrue($second->valid);
        $this->assertCount(1, $transport->sentRequests());

        $service->forgetCache($connection);

        $this->assertFalse(Cache::has('flickr_token_health:'.$connection->connection_key));
    }
}

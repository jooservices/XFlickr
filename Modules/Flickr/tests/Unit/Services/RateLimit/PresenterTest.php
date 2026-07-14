<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services\RateLimit;

use Illuminate\Support\Facades\Redis;
use Modules\Flickr\Services\RateLimit\Presenter;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;

final class PresenterTest extends TestCase
{
    use CreatesFlickrConnection;

    public function test_present_returns_limiter_state_and_window_reset_metadata(): void
    {
        $this->requiresRedis();

        $connection = $this->createFlickrConnection();
        $this->cleanLimiterKeys($connection->connection_key);

        $windowKey = "xflickr:req:{$connection->connection_key}:window";
        $nowMs = (int) floor(microtime(true) * 1000);
        Redis::zadd($windowKey, $nowMs - 1_000, 'req-1');

        $presented = app(Presenter::class)->present($connection->connection_key);

        $this->assertArrayHasKey('requests_used', $presented);
        $this->assertArrayHasKey('max_requests_per_hour', $presented);
        $this->assertArrayHasKey('requests_remaining', $presented);
        $this->assertArrayHasKey('window_seconds', $presented);
        $this->assertArrayHasKey('window_reset_at', $presented);
        $this->assertArrayHasKey('window_seconds_remaining', $presented);
        $this->assertArrayHasKey('global_pause', $presented);
        $this->assertSame(1, $presented['requests_used']);
        $this->assertNotNull($presented['window_reset_at']);
        $this->assertGreaterThan(0, $presented['window_seconds_remaining']);
    }

    public function test_present_returns_empty_window_reset_when_no_requests_recorded(): void
    {
        $this->requiresRedis();

        $connection = $this->createFlickrConnection();
        $this->cleanLimiterKeys($connection->connection_key);

        $presented = app(Presenter::class)->present($connection->connection_key);

        $this->assertNull($presented['window_reset_at']);
        $this->assertSame(0, $presented['window_seconds_remaining']);
        $this->assertSame(0, $presented['requests_used']);
    }
}

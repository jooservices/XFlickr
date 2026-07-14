<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services\RateLimit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Modules\Crawler\Enums\ApiOutcome;
use Modules\Crawler\Models\ApiLog;
use Modules\Flickr\Services\RateLimit\UsageQueryService;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;

final class UsageQueryServiceTest extends TestCase
{
    use CreatesFlickrConnection;

    public function test_usage_rejects_unknown_connection_key(): void
    {
        $this->expectException(ValidationException::class);

        try {
            app(UsageQueryService::class)->usage('unknown@N01', 24);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('connection_key', $exception->errors());

            throw $exception;
        }
    }

    public function test_usage_clamps_hours_to_supported_range(): void
    {
        $this->requiresRedis();

        $connection = $this->createFlickrConnection();
        $this->cleanLimiterKeys($connection->connection_key);

        $usage = app(UsageQueryService::class)->usage($connection->connection_key, 999);

        $this->assertSame(48, $usage['hours']);
        $this->assertCount(48, $usage['buckets']);
    }

    public function test_usage_returns_zero_filled_buckets_when_no_logs_exist(): void
    {
        $this->requiresRedis();

        CarbonImmutable::setTestNow('2026-06-23 10:30:00');

        try {
            $connection = $this->createFlickrConnection();
            $this->cleanLimiterKeys($connection->connection_key);

            $usage = app(UsageQueryService::class)->usage($connection->connection_key, 3);

            $this->assertSame($connection->connection_key, $usage['connection_key']);
            $this->assertSame(3, $usage['hours']);
            $this->assertCount(3, $usage['buckets']);
            $this->assertSame(0, $usage['buckets'][0]['requests']);
            $this->assertFalse($usage['buckets'][0]['is_current']);
            $this->assertTrue($usage['buckets'][2]['is_current']);
            $this->assertArrayHasKey('requests_used', $usage['rate_limit']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_usage_aggregates_api_logs_by_hour_and_caches_result(): void
    {
        $this->requiresRedis();

        CarbonImmutable::setTestNow('2026-06-23 10:30:00');

        try {
            $connection = $this->createFlickrConnection();
            $this->cleanLimiterKeys($connection->connection_key);
            Cache::flush();

            $this->createApiLog($connection->connection_key, '2026-06-23 08:15:00');
            $this->createApiLog($connection->connection_key, '2026-06-23 08:45:00');
            $this->createApiLog($connection->connection_key, '2026-06-23 10:05:00');

            $service = app(UsageQueryService::class);
            $first = $service->usage($connection->connection_key, 3);
            $second = $service->usage($connection->connection_key, 3);

            $this->assertSame(2, $first['buckets'][0]['requests']);
            $this->assertSame(0, $first['buckets'][1]['requests']);
            $this->assertSame(1, $first['buckets'][2]['requests']);
            $this->assertTrue(Cache::has("xflickr:rate-limit:usage:{$connection->connection_key}:3"));
            $this->assertSame($first, $second);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function createApiLog(string $connectionKey, string $createdAt): ApiLog
    {
        return ApiLog::query()->forceCreate([
            'connection_key' => $connectionKey,
            'xflickr_crawl_run_id' => null,
            'xflickr_crawl_target_id' => null,
            'api_method' => 'flickr.test.echo',
            'outcome' => ApiOutcome::Success,
            'latency_ms' => 42,
            'error_code' => null,
            'error_message' => null,
            'context' => null,
            'created_at' => CarbonImmutable::parse($createdAt),
        ]);
    }
}

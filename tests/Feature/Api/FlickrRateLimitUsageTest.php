<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Carbon\CarbonImmutable;
use JOOservices\XFlickrCrawler\Enums\ApiOutcome;
use JOOservices\XFlickrCrawler\Models\ApiLog;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class FlickrRateLimitUsageTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_usage_requires_connection_key(): void
    {
        $response = $this->getJson('/api/v1/flickr/rate-limit/usage');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['connection_key']);
    }

    public function test_usage_rejects_unknown_connection_key(): void
    {
        $response = $this->getJson('/api/v1/flickr/rate-limit/usage?connection_key=unknown@N01');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['connection_key']);
    }

    public function test_usage_returns_zero_filled_buckets_when_no_logs(): void
    {
        $connection = $this->createFlickrConnection([
            'connection_key' => '12037949629@N01',
        ]);

        $response = $this->getJson('/api/v1/flickr/rate-limit/usage?connection_key='.$connection->connection_key.'&hours=24');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'connection_key',
                'hours',
                'generated_at',
                'max_requests_per_hour',
                'buckets' => [
                    [
                        'hour_start',
                        'requests',
                        'is_current',
                    ],
                ],
                'rate_limit' => [
                    'requests_used',
                    'max_requests_per_hour',
                    'requests_remaining',
                    'window_seconds',
                    'window_reset_at',
                    'window_seconds_remaining',
                    'global_pause',
                    'cooldown_until',
                    'cooldown_seconds_remaining',
                ],
            ],
        ]);
        $response->assertJsonPath('data.connection_key', $connection->connection_key);
        $response->assertJsonPath('data.hours', 24);
        $response->assertJsonCount(24, 'data.buckets');

        $buckets = $response->json('data.buckets');
        $this->assertIsArray($buckets);
        $this->assertSame(0, $buckets[0]['requests']);
        $this->assertTrue($buckets[23]['is_current']);
        $this->assertFalse($buckets[0]['is_current']);
    }

    public function test_usage_aggregates_api_logs_by_hour(): void
    {
        CarbonImmutable::setTestNow('2026-06-23 10:30:00');

        try {
            $connection = $this->createFlickrConnection([
                'connection_key' => '12037949629@N01',
            ]);

            $this->createApiLog($connection->connection_key, '2026-06-23 08:15:00');
            $this->createApiLog($connection->connection_key, '2026-06-23 08:45:00');
            $this->createApiLog($connection->connection_key, '2026-06-23 10:05:00');

            $response = $this->getJson('/api/v1/flickr/rate-limit/usage?connection_key='.$connection->connection_key.'&hours=3');

            $response->assertOk();
            $response->assertJsonPath('data.hours', 3);
            $response->assertJsonCount(3, 'data.buckets');
            $response->assertJsonPath('data.buckets.0.requests', 2);
            $response->assertJsonPath('data.buckets.0.is_current', false);
            $response->assertJsonPath('data.buckets.1.requests', 0);
            $response->assertJsonPath('data.buckets.1.is_current', false);
            $response->assertJsonPath('data.buckets.2.requests', 1);
            $response->assertJsonPath('data.buckets.2.is_current', true);
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

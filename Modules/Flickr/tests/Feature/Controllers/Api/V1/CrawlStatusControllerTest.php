<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Feature\Controllers\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Crawler\Enums\ApiOutcome;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Models\ApiLog;
use Modules\Crawler\Models\CrawlRun;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class CrawlStatusControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_crawl_summary_includes_rate_limit_shape(): void
    {
        $connection = $this->createFlickrConnection([
            'connection_key' => '12037949629@N01',
            'username' => 'testuser',
            'fullname' => 'Test User',
            'app_profile' => 'main',
        ]);

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/crawl/summary');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'connection_key',
                'runs' => ['running', 'completed', 'failed'],
                'pending_targets',
                'global_pause',
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
        $response->assertJsonPath('data.connection_key', '12037949629@N01');
    }

    public function test_crawl_runs_returns_paginated_runs_for_connection(): void
    {
        $connection = $this->createFlickrConnection();

        CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'crawl_type' => 'photos',
            'subject_nsid' => '999@N01',
            'status' => CrawlRunStatus::Completed,
            'photos_discovered' => 12,
            'api_calls' => 3,
            'started_at' => CarbonImmutable::parse('2026-06-23 08:00:00'),
            'finished_at' => CarbonImmutable::parse('2026-06-23 08:05:00'),
        ]);

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/crawl/runs?sort=started_at&direction=desc&per_page=10&page=1');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'crawl_type',
                    'subject_nsid',
                    'status',
                    'photos_discovered',
                    'api_calls',
                    'started_at',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'sort', 'direction'],
        ]);
        $response->assertJsonPath('data.0.crawl_type', 'photos');
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_crawl_runs_falls_back_to_default_sort_for_invalid_sort_field(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/crawl/runs?sort=not-a-column');

        $response->assertOk();
        $response->assertJsonPath('meta.sort', 'id');
    }

    public function test_crawl_logs_returns_paginated_api_logs(): void
    {
        $connection = $this->createFlickrConnection();

        ApiLog::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'xflickr_crawl_run_id' => null,
            'xflickr_crawl_target_id' => null,
            'api_method' => 'flickr.test.echo',
            'outcome' => ApiOutcome::Success,
            'latency_ms' => 25,
            'error_code' => null,
            'error_message' => null,
            'context' => null,
            'created_at' => CarbonImmutable::parse('2026-06-23 09:00:00'),
        ]);

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/crawl/logs?per_page=25&page=1');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                [
                    'api_method',
                    'outcome',
                    'latency_ms',
                    'created_at',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $response->assertJsonPath('data.0.api_method', 'flickr.test.echo');
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_crawl_endpoints_return_not_found_for_unknown_public_id(): void
    {
        $unknownPublicId = (string) Str::uuid();

        $this->getJson('/api/v1/flickr/accounts/'.$unknownPublicId.'/crawl/summary')->assertNotFound();
        $this->getJson('/api/v1/flickr/accounts/'.$unknownPublicId.'/crawl/runs')->assertNotFound();
        $this->getJson('/api/v1/flickr/accounts/'.$unknownPublicId.'/crawl/logs')->assertNotFound();
    }

    public function test_crawl_logs_rejects_invalid_pagination(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/crawl/logs?per_page=0');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['per_page']);
    }
}

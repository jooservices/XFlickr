<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services;

use Modules\Crawler\Enums\ApiOutcome;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Models\ApiLog;
use Modules\Crawler\Models\CrawlRun;
use Modules\Flickr\Services\CrawlStatusQueryService;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;

final class CrawlStatusQueryServiceTest extends TestCase
{
    use CreatesFlickrConnection;

    private CrawlStatusQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CrawlStatusQueryService::class);
    }

    public function test_summary_returns_connection_shape(): void
    {
        $this->requiresRedis();

        $connection = $this->createFlickrConnection();
        CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'crawl_type' => CrawlType::Contacts->value,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        $summary = $this->service->summary($connection);

        $this->assertSame($connection->connection_key, $summary['connection_key']);
        $this->assertArrayHasKey('runs', $summary);
        $this->assertArrayHasKey('pending_targets', $summary);
        $this->assertArrayHasKey('global_pause', $summary);
        $this->assertArrayHasKey('rate_limit', $summary);
        $this->assertSame(1, $summary['runs']['running']);
        $this->assertSame(0, $summary['runs']['completed']);
        $this->assertSame(0, $summary['runs']['failed']);
    }

    public function test_runs_falls_back_to_id_sort_for_invalid_sort(): void
    {
        $connection = $this->createFlickrConnection();
        CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'crawl_type' => CrawlType::Contacts->value,
            'status' => CrawlRunStatus::Completed,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $payload = $this->service->runs($connection, 'not-a-column', 'desc', 10, 1);

        $this->assertSame('id', $payload['meta']['sort']);
        $this->assertSame(10, $payload['meta']['per_page']);
        $this->assertCount(1, $payload['data']);
    }

    public function test_logs_returns_paginated_meta(): void
    {
        $connection = $this->createFlickrConnection();
        ApiLog::query()->create([
            'connection_key' => $connection->connection_key,
            'api_method' => 'flickr.test.echo',
            'outcome' => ApiOutcome::Success,
            'created_at' => now(),
        ]);

        $payload = $this->service->logs($connection, 5, 1);

        $this->assertSame(5, $payload['meta']['per_page']);
        $this->assertSame(1, $payload['meta']['total']);
        $this->assertCount(1, $payload['data']);
    }
}

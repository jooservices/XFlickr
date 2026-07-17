<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Feature\Controllers\Api\V1;

use JOOservices\LaravelLogging\Facades\ActivityLog;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class ActivityFeedControllerTest extends TestCase
{
    use SafeRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ActivityLogRecord::query()->delete();
    }

    protected function tearDown(): void
    {
        ActivityLogRecord::query()->delete();

        parent::tearDown();
    }

    public function test_index_returns_paginated_activity_with_facets(): void
    {
        ActivityLog::domain()
            ->action('crawler.run.failed')
            ->bySystem()
            ->correlationId('42')
            ->batchId('42')
            ->properties([
                'run_id' => 42,
                'connection_key_fp' => 'abcdef123456',
                'reason' => 'All crawl targets failed',
            ])
            ->level('warning')
            ->sync()
            ->dispatch();

        ActivityLog::audit()
            ->action('settings.spider_mode.updated')
            ->bySystem()
            ->properties(['spider.enabled' => true])
            ->level('info')
            ->sync()
            ->dispatch();

        $response = $this->getJson('/api/v1/operations/activities');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'type',
                    'level',
                    'action',
                    'message',
                    'actor',
                    'subject',
                    'correlation_id',
                    'trace_id',
                    'properties',
                    'context',
                    'changes',
                    'occurred_at',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
                'facets' => [
                    'by_level',
                ],
            ],
        ]);

        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertGreaterThanOrEqual(2, (int) ($payload['meta']['total'] ?? 0));

        $serialized = json_encode($payload);
        $this->assertIsString($serialized);
        $this->assertStringNotContainsString('secret-connection', $serialized);
        $this->assertStringNotContainsString('connection_key":', $serialized);
    }

    public function test_index_filters_by_type_and_correlation_id(): void
    {
        ActivityLog::domain()
            ->action('crawler.run.started')
            ->bySystem()
            ->correlationId('99')
            ->properties(['run_id' => 99])
            ->level('info')
            ->sync()
            ->dispatch();

        ActivityLog::domain()
            ->action('crawler.run.failed')
            ->bySystem()
            ->correlationId('100')
            ->properties(['run_id' => 100])
            ->level('warning')
            ->sync()
            ->dispatch();

        ActivityLog::audit()
            ->action('settings.runtime_config.saved')
            ->bySystem()
            ->correlationId('99')
            ->level('info')
            ->sync()
            ->dispatch();

        $response = $this->getJson('/api/v1/operations/activities?type=domain&correlation_id=99');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame('crawler.run.started', $data[0]['action']);
        $this->assertSame('99', $data[0]['correlation_id']);
        $this->assertSame('domain', $data[0]['type']);
    }
}

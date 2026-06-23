<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class CrawlSummaryRateLimitTest extends TestCase
{
    use CreatesFlickrConnection;
    use RefreshDatabase;

    public function test_crawl_summary_includes_rate_limit_shape(): void
    {
        $connection = $this->createFlickrConnection([
            'connection_key' => '12037949629@N01',
            'username' => 'testuser',
            'fullname' => 'Test User',
            'app_profile' => 'main',
        ]);

        $response = $this->getJson('/api/flickr/accounts/'.$connection->public_id.'/crawl/summary');

        $response->assertOk();
        $response->assertJsonStructure([
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
        ]);
        $response->assertJsonPath('connection_key', '12037949629@N01');
    }
}

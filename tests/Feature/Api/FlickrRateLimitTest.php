<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class FlickrRateLimitTest extends TestCase
{
    use CreatesFlickrConnection;
    use RefreshDatabase;

    public function test_rate_limit_snapshot_includes_accounts_and_active_key(): void
    {
        $connection = $this->createFlickrConnection([
            'connection_key' => '12037949629@N01',
            'username' => 'testuser',
            'fullname' => 'Test User',
            'app_profile' => 'main',
        ]);

        $response = $this->getJson('/api/flickr/rate-limit');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'generated_at',
                'active_connection_key',
                'accounts' => [
                    [
                        'account' => [
                            'public_id',
                            'nsid',
                            'username',
                            'fullname',
                            'app_profile',
                            'connected_at',
                            'is_active',
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
                ],
            ],
        ]);
        $response->assertJsonPath('data.active_connection_key', '12037949629@N01');
        $response->assertJsonPath('data.accounts.0.account.nsid', $connection->connection_key);
    }
}

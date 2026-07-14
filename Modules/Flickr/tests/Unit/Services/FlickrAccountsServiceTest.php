<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services;

use Modules\Flickr\Services\FlickrAccountsService;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;

final class FlickrAccountsServiceTest extends TestCase
{
    use CreatesFlickrConnection;

    public function test_list_accounts_exposes_connected_account(): void
    {
        $connection = $this->createFlickrConnection([
            'username' => fake()->userName(),
        ]);

        $accounts = app(FlickrAccountsService::class)->listAccounts();

        $this->assertCount(1, $accounts);
        $this->assertSame($connection->connection_key, $accounts[0]['nsid']);
        $this->assertTrue($accounts[0]['is_connected']);
    }

    public function test_active_connection_returns_active_account(): void
    {
        $connection = $this->createFlickrConnection([
            'is_active' => true,
        ]);

        $active = app(FlickrAccountsService::class)->activeConnection();

        $this->assertNotNull($active);
        $this->assertSame($connection->connection_key, $active->connection_key);
    }

    public function test_rate_limit_present_returns_limiter_payload(): void
    {
        $this->requiresRedis();

        $connection = $this->createFlickrConnection();
        $this->cleanLimiterKeys($connection->connection_key);

        $presented = app(FlickrAccountsService::class)->rateLimitPresent($connection->connection_key);

        $this->assertArrayHasKey('requests_used', $presented);
        $this->assertArrayHasKey('max_requests_per_hour', $presented);
        $this->assertArrayHasKey('requests_remaining', $presented);
    }

    public function test_probe_token_health_returns_result_for_connected_account(): void
    {
        $connection = $this->createFlickrConnection();

        $result = app(FlickrAccountsService::class)->probeTokenHealth($connection, useCache: false);

        $this->assertTrue($result->valid);
    }

    public function test_crawl_status_runs_returns_paginated_shape(): void
    {
        $connection = $this->createFlickrConnection();

        $runs = app(FlickrAccountsService::class)->crawlStatusRuns($connection, 'id', 'desc', 10, 1);

        $this->assertArrayHasKey('data', $runs);
        $this->assertArrayHasKey('meta', $runs);
        $this->assertSame(10, $runs['meta']['per_page']);
    }

    public function test_list_app_profiles_returns_array_list(): void
    {
        $profiles = app(FlickrAccountsService::class)->listAppProfiles();

        $this->assertIsArray($profiles);
    }
}

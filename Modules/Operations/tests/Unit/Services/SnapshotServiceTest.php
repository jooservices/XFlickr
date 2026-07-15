<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Operations\Services\SnapshotService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class SnapshotServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    #[Test]
    public function dashboard_builds_global_and_per_account_shape(): void
    {
        $this->createFlickrConnection([
            'connection_key' => 'a@N01',
            'username' => 'alpha',
            'fullname' => 'Alpha',
        ]);

        Cache::forget('xflickr:dashboard:snapshot');

        $snapshot = app(SnapshotService::class)->dashboard();

        $this->assertSame(
            ['generated_at', 'global', 'accounts', 'databases', 'alerts'],
            array_keys($snapshot),
        );
        $this->assertSame(1, $snapshot['global']['accounts']);
        $this->assertArrayHasKey('stored_files', $snapshot['global']);
        $this->assertArrayHasKey('any_cooldown', $snapshot['alerts']);
        $this->assertCount(1, $snapshot['accounts']);
        $this->assertSame('a@N01', $snapshot['accounts'][0]['account']['nsid']);
        $this->assertArrayHasKey('rate_limit', $snapshot['accounts'][0]);
        $this->assertArrayHasKey('transfers', $snapshot['accounts'][0]);
        $this->assertArrayHasKey('databases', $snapshot);
        $this->assertArrayHasKey('mysql', $snapshot['databases']);
        $this->assertArrayHasKey('mongodb', $snapshot['databases']);
        $this->assertArrayHasKey('history', $snapshot['databases']);
        $this->assertArrayHasKey('database_unreachable', $snapshot['alerts']);
        $this->assertArrayHasKey('mysql_connections_high', $snapshot['alerts']);
    }

    #[Test]
    public function operations_returns_operations_collections(): void
    {
        $this->createFlickrConnection();

        $snapshot = app(SnapshotService::class)->operations();

        $this->assertSame(
            [
                'overview',
                'queues',
                'target_breakdown',
                'spider',
                'dependencies',
                'databases',
                'accounts',
                'fetch_runs',
                'download_batches',
                'upload_batches',
            ],
            array_keys($snapshot),
        );

        $this->assertArrayHasKey('runs_running', $snapshot['overview']);
        $this->assertArrayHasKey('pending_targets', $snapshot['overview']);
        $this->assertArrayHasKey('downloads_active', $snapshot['overview']);
        $this->assertArrayHasKey('uploads_active', $snapshot['overview']);
        $this->assertArrayHasKey('failed_transfers_24h', $snapshot['overview']);
        $this->assertArrayHasKey('accounts_in_cooldown', $snapshot['overview']);
        $this->assertArrayHasKey('global_pause', $snapshot['overview']);
        $this->assertArrayHasKey('xflickr', $snapshot['queues']);
        $this->assertIsArray($snapshot['target_breakdown']);
        $this->assertIsArray($snapshot['spider']);

        $this->assertArrayHasKey('mysql', $snapshot['dependencies']);
        $this->assertArrayHasKey('redis', $snapshot['dependencies']);
        $this->assertArrayHasKey('mongodb', $snapshot['dependencies']);
        $this->assertArrayHasKey('mysql', $snapshot['databases']);
        $this->assertArrayHasKey('mongodb', $snapshot['databases']);
        $this->assertArrayHasKey('history', $snapshot['databases']);
        $this->assertIsArray($snapshot['databases']['history']);

        $this->assertCount(1, $snapshot['accounts']);
        $this->assertArrayHasKey('pending_targets', $snapshot['accounts'][0]);
        $this->assertArrayHasKey('rate_limit', $snapshot['accounts'][0]);
    }
}

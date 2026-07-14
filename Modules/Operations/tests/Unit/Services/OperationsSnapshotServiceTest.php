<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Unit\Services;

use Modules\Operations\Services\OperationsSnapshotService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class OperationsSnapshotServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    #[Test]
    public function snapshot_returns_operations_collections(): void
    {
        $this->createFlickrConnection();

        $snapshot = app(OperationsSnapshotService::class)->snapshot();

        $this->assertArrayHasKey('overview', $snapshot);
        $this->assertArrayHasKey('dependencies', $snapshot);
        $this->assertArrayHasKey('databases', $snapshot);
        $this->assertArrayHasKey('accounts', $snapshot);
        $this->assertArrayHasKey('fetch_runs', $snapshot);
        $this->assertArrayHasKey('download_batches', $snapshot);
        $this->assertArrayHasKey('upload_batches', $snapshot);

        $this->assertArrayHasKey('runs_running', $snapshot['overview']);
        $this->assertArrayHasKey('pending_targets', $snapshot['overview']);
        $this->assertArrayHasKey('downloads_active', $snapshot['overview']);
        $this->assertArrayHasKey('uploads_active', $snapshot['overview']);
        $this->assertArrayHasKey('failed_transfers_24h', $snapshot['overview']);
        $this->assertArrayHasKey('accounts_in_cooldown', $snapshot['overview']);

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

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

        $this->assertArrayHasKey('fetch_runs', $snapshot);
        $this->assertArrayHasKey('download_batches', $snapshot);
        $this->assertArrayHasKey('upload_batches', $snapshot);
        $this->assertIsArray($snapshot['fetch_runs']);
        $this->assertIsArray($snapshot['download_batches']);
        $this->assertIsArray($snapshot['upload_batches']);
    }
}

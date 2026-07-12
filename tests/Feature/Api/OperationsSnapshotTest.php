<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class OperationsSnapshotTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_snapshot_returns_operations_payload(): void
    {
        $this->createFlickrConnection();

        $response = $this->getJson('/api/v1/operations/snapshot');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'fetch_runs',
                'download_batches',
                'upload_batches',
            ],
        ]);
    }
}

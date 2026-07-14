<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Feature\Controllers\Api\V1;

use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class OperationsSnapshotControllerTest extends TestCase
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
                'overview' => [
                    'runs_running',
                    'pending_targets',
                    'downloads_active',
                    'uploads_active',
                    'failed_transfers_24h',
                    'accounts_in_cooldown',
                ],
                'dependencies' => [
                    'mysql',
                    'redis',
                    'mongodb',
                ],
                'databases' => [
                    'mysql',
                    'mongodb',
                    'history',
                ],
                'accounts',
                'fetch_runs',
                'download_batches',
                'upload_batches',
            ],
        ]);
    }
}

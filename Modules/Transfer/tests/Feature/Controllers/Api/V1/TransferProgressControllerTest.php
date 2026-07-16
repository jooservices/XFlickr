<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Feature\Controllers\Api\V1;

use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Tests\TestCase;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;

final class TransferProgressControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_index_returns_empty_list_for_connection_without_batches(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->getJson("/api/v1/flickr/accounts/{$connection->connection_key}/transfers");

        $response->assertOk();
        $response->assertJsonPath('data', []);
    }

    public function test_show_returns_404_for_batch_belonging_to_different_connection(): void
    {
        $connection = $this->createFlickrConnection();
        $otherConnection = $this->createFlickrConnection();

        $batch = TransferBatch::factory()->create([
            'connection_key' => $otherConnection->connection_key,
        ]);

        $response = $this->getJson("/api/v1/flickr/accounts/{$connection->connection_key}/transfers/{$batch->id}");

        $response->assertNotFound();
    }
}

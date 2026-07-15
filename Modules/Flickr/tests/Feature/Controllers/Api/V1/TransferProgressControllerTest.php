<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Feature\Controllers\Api\V1;

use Modules\Storage\Models\TransferBatch;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

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

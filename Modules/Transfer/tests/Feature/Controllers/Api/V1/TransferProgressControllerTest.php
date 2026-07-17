<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Feature\Controllers\Api\V1;

use Database\Factories\Crawler\PhotoFactory;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
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

    public function test_show_includes_catalog_photo_for_a_transfer_item(): void
    {
        $connection = $this->createFlickrConnection();
        $photo = PhotoFactory::new()->create([
            'flickr_photo_id' => 'photo-in-history',
            'owner_nsid' => 'owner@N01',
            'title' => 'Photo in transfer history',
            'secret' => 'secret123',
            'server' => '1234',
        ]);
        $batch = TransferBatch::factory()->create([
            'connection_key' => $connection->connection_key,
            'total_count' => 1,
        ]);
        TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => $photo->flickr_photo_id,
        ]);

        $response = $this->getJson("/api/v1/flickr/accounts/{$connection->connection_key}/transfers/{$batch->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.items.0.flickr_photo_id', $photo->flickr_photo_id)
            ->assertJsonPath('data.items.0.photo.flickr_photo_id', $photo->flickr_photo_id)
            ->assertJsonPath('data.items.0.photo.owner_nsid', $photo->owner_nsid)
            ->assertJsonPath('data.items.0.photo.title', $photo->title)
            ->assertJsonPath('data.items.0.photo.secret', $photo->secret)
            ->assertJsonPath('data.items.0.photo.server', $photo->server);
    }

    public function test_item_index_returns_photo_transfer_history_rows(): void
    {
        $connection = $this->createFlickrConnection();
        $photo = PhotoFactory::new()->create([
            'flickr_photo_id' => 'photo-history-row',
            'owner_nsid' => 'owner@N02',
        ]);
        $batch = TransferBatch::factory()->create([
            'connection_key' => $connection->connection_key,
            'type' => 'upload',
            'group_label' => 'Summer album',
        ]);
        TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => $photo->flickr_photo_id,
            'status' => 'failed',
        ]);

        $response = $this->getJson("/api/v1/flickr/accounts/{$connection->connection_key}/transfers/items?type=upload&status=failed");

        $response
            ->assertOk()
            ->assertJsonPath('data.0.flickr_photo_id', $photo->flickr_photo_id)
            ->assertJsonPath('data.0.photo.flickr_photo_id', $photo->flickr_photo_id)
            ->assertJsonPath('data.0.batch.id', $batch->id)
            ->assertJsonPath('data.0.batch.type', 'upload')
            ->assertJsonPath('data.0.batch.group_label', 'Summer album');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Feature\Controllers\Api\V1;

use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class TransferProgressControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_active_index_returns_batches_without_reconciling_counts(): void
    {
        $connection = $this->createFlickrConnection();
        $timestamp = now()->subMinutes(5);

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'friend@N01',
            'status' => 'running',
            'total_count' => 1,
            'completed_count' => 0,
            'failed_count' => 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => 'completed',
        ]);

        $originalUpdatedAt = $batch->fresh()->updated_at?->toDateTimeString();

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/transfers?active=1');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $batch->id);
        $response->assertJsonPath('data.0.completed_count', 0);
        $response->assertJsonPath('data.0.status', 'running');

        $batch->refresh();
        $this->assertSame(0, $batch->completed_count);
        $this->assertSame('running', $batch->status);
        $this->assertSame($originalUpdatedAt, $batch->updated_at?->toDateTimeString());
    }

    public function test_show_returns_batch_detail_payload(): void
    {
        $connection = $this->createFlickrConnection();
        $batch = TransferBatch::factory()->create([
            'connection_key' => $connection->connection_key,
            'type' => 'download',
            'status' => 'running',
            'total_count' => 1,
        ]);
        TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => (string) fake()->unique()->numerify('#########'),
            'status' => 'pending',
        ]);

        $response = $this->getJson(
            '/api/v1/flickr/accounts/'.$connection->public_id.'/transfers/'.$batch->id,
        );

        $response->assertOk();
        $response->assertJsonPath('data.batch.id', $batch->id);
        $response->assertJsonCount(1, 'data.items');
    }
}

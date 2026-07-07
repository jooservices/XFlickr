<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TransferBatch;
use App\Models\TransferItem;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class TransferProgressQueryServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_active_index_returns_batches_without_reconciling_counts(): void
    {
        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);
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

        $response = $this->getJson('/api/flickr/accounts/'.$connection->public_id.'/transfers?active=1');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $batch->id);
        $response->assertJsonPath('data.0.completed_count', 0);
        $response->assertJsonPath('data.0.status', 'running');

        $batch->refresh();
        $this->assertSame(0, $batch->completed_count);
        $this->assertSame('running', $batch->status);
        $this->assertSame($originalUpdatedAt, $batch->updated_at?->toDateTimeString());
    }
}

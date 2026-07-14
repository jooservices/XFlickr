<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Services\TransferProgressQueryService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class TransferProgressQueryServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    private TransferProgressQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TransferProgressQueryService::class);
    }

    public function test_show_returns_null_for_foreign_batch(): void
    {
        $connection = $this->createFlickrConnection();
        $other = $this->createFlickrConnection();

        $batch = TransferBatch::factory()->create([
            'connection_key' => $other->connection_key,
            'type' => 'download',
        ]);

        $this->assertNull($this->service->show($connection, $batch));
    }

    public function test_show_returns_batch_and_items_for_matching_connection(): void
    {
        $connection = $this->createFlickrConnection();
        $batch = TransferBatch::factory()->create([
            'connection_key' => $connection->connection_key,
            'type' => 'download',
            'status' => TransferBatchStatus::Running->value,
            'total_count' => 1,
        ]);
        TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => TransferItemStatus::Pending->value,
        ]);

        $payload = $this->service->show($connection, $batch->fresh());

        $this->assertNotNull($payload);
        $this->assertSame($batch->id, $payload['batch']['id']);
        $this->assertCount(1, $payload['items']);
    }

    public function test_index_filters_by_status_type_and_active_window(): void
    {
        $connection = $this->createFlickrConnection();

        TransferBatch::factory()->create([
            'connection_key' => $connection->connection_key,
            'type' => 'upload',
            'status' => TransferBatchStatus::Running->value,
            'updated_at' => now(),
        ]);
        TransferBatch::factory()->create([
            'connection_key' => $connection->connection_key,
            'type' => 'download',
            'status' => TransferBatchStatus::Completed->value,
            'updated_at' => now()->subDays(2),
        ]);

        $activeUploads = $this->service->index(
            $connection,
            status: null,
            type: 'upload',
            active: true,
            sort: 'id',
            direction: 'desc',
            limit: 10,
        );

        $this->assertCount(1, $activeUploads['data']);
        $this->assertSame('upload', $activeUploads['data'][0]['type']);
    }
}

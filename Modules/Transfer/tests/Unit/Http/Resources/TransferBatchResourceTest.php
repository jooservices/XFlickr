<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Http\Resources;

use Illuminate\Http\Request;
use Modules\Transfer\Http\Resources\TransferBatchResource;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Tests\TestCase;

final class TransferBatchResourceTest extends TestCase
{
    public function test_resource_formats_model_correctly(): void
    {
        $batch = TransferBatch::factory()->create();

        $resource = TransferBatchResource::make($batch);
        $resolved = $resource->resolve(app(Request::class));

        $this->assertSame($batch->id, $resolved['id']);
        $this->assertSame($batch->type, $resolved['type']);
        $this->assertSame($batch->status, $resolved['status']);
        $this->assertSame($batch->total_count, $resolved['total_count']);
        $this->assertSame($batch->completed_count, $resolved['completed_count']);
        $this->assertSame($batch->failed_count, $resolved['failed_count']);
        $this->assertSame($batch->connection_key, $resolved['connection_key']);
        $this->assertSame($batch->subject_nsid, $resolved['subject_nsid']);
        $this->assertArrayHasKey('created_at', $resolved);
        $this->assertArrayHasKey('updated_at', $resolved);
        $this->assertArrayHasKey('group_type', $resolved);
        $this->assertArrayHasKey('group_id', $resolved);
        $this->assertArrayHasKey('group_label', $resolved);
        $this->assertArrayHasKey('storage_account_id', $resolved);
        $this->assertArrayHasKey('sample_error', $resolved);
    }

    public function test_resource_passes_through_arrays(): void
    {
        $data = [
            'id' => 42,
            'type' => 'download',
            'status' => 'completed',
        ];

        $resource = TransferBatchResource::make($data);
        $resolved = $resource->resolve(app(Request::class));

        $this->assertSame($data, $resolved);
    }
}

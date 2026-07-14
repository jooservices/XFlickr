<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Http\Resources;

use Illuminate\Http\Request;
use Modules\Transfer\Database\Factories\TransferBatchFactory;
use Modules\Transfer\Http\Resources\TransferBatchResource;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class TransferBatchResourceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_it_serializes_transfer_batch_model(): void
    {
        $batch = TransferBatchFactory::new()->create([
            'type' => 'upload',
            'status' => 'pending',
            'total_count' => 5,
            'completed_count' => 2,
            'failed_count' => 1,
            'group_type' => 'contact',
            'group_id' => 'group-1',
            'group_label' => 'Contact batch',
        ]);
        $batch->setAttribute('sample_error', 'timeout');

        $payload = (new TransferBatchResource($batch))->toArray(Request::create('/'));

        $this->assertSame($batch->id, $payload['id']);
        $this->assertSame('upload', $payload['type']);
        $this->assertSame('pending', $payload['status']);
        $this->assertSame(5, $payload['total_count']);
        $this->assertSame(2, $payload['completed_count']);
        $this->assertSame(1, $payload['failed_count']);
        $this->assertSame($batch->connection_key, $payload['connection_key']);
        $this->assertSame($batch->subject_nsid, $payload['subject_nsid']);
        $this->assertSame('contact', $payload['group_type']);
        $this->assertSame('group-1', $payload['group_id']);
        $this->assertSame('Contact batch', $payload['group_label']);
        $this->assertSame($batch->storage_account_id, $payload['storage_account_id']);
        $this->assertSame('timeout', $payload['sample_error']);
        $this->assertIsString($payload['created_at']);
        $this->assertIsString($payload['updated_at']);
    }

    public function test_it_passes_through_array_payload(): void
    {
        $array = [
            'id' => 99,
            'type' => 'download',
            'status' => 'completed',
        ];

        $payload = (new TransferBatchResource($array))->toArray(Request::create('/'));

        $this->assertSame($array, $payload);
    }
}

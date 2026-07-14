<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Http\Resources;

use Illuminate\Http\Request;
use Modules\Transfer\Database\Factories\TransferBatchFactory;
use Modules\Transfer\Http\Resources\TransferBatchDetailResource;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class TransferBatchDetailResourceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_it_wraps_batch_and_items(): void
    {
        $batch = TransferBatchFactory::new()->create([
            'type' => 'download',
            'status' => 'running',
            'total_count' => 1,
            'completed_count' => 0,
            'failed_count' => 0,
        ]);
        $items = [
            [
                'id' => fake()->numberBetween(1, 100),
                'flickr_photo_id' => (string) fake()->unique()->numerify('#########'),
                'status' => 'pending',
            ],
        ];

        $payload = (new TransferBatchDetailResource([
            'batch' => $batch,
            'items' => $items,
        ]))->toArray(Request::create('/'));

        $this->assertSame($batch->id, $payload['batch']['id']);
        $this->assertSame('download', $payload['batch']['type']);
        $this->assertSame($items, $payload['items']);
    }
}

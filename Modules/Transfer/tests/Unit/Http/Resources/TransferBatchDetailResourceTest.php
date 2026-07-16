<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Http\Resources;

use Illuminate\Http\Request;
use Modules\Transfer\Http\Resources\TransferBatchDetailResource;
use Modules\Transfer\Tests\TestCase;

final class TransferBatchDetailResourceTest extends TestCase
{
    public function test_detail_resource_includes_batch_and_items(): void
    {
        $batchData = [
            'id' => 1,
            'type' => 'download',
            'status' => 'running',
        ];

        $items = [
            ['id' => 10, 'flickr_photo_id' => 'photo-1', 'status' => 'completed'],
            ['id' => 11, 'flickr_photo_id' => 'photo-2', 'status' => 'pending'],
        ];

        $payload = [
            'batch' => $batchData,
            'items' => $items,
        ];

        $resource = TransferBatchDetailResource::make($payload);
        $resolved = $resource->resolve(app(Request::class));

        $this->assertArrayHasKey('batch', $resolved);
        $this->assertArrayHasKey('items', $resolved);
        $this->assertSame($batchData, $resolved['batch']);
        $this->assertSame($items, $resolved['items']);
    }
}

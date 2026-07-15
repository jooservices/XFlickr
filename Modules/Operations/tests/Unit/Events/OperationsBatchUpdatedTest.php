<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Unit\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Modules\Operations\Events\OperationsBatchUpdated;
use Tests\TestCase;

final class OperationsBatchUpdatedTest extends TestCase
{
    public function test_event_broadcasts_on_operations_channel(): void
    {
        $event = new OperationsBatchUpdated(['id' => 1, 'status' => 'running']);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-operations', $channels[0]->name);
    }

    public function test_broadcast_as_returns_correct_name(): void
    {
        $event = new OperationsBatchUpdated(['id' => 1]);

        $this->assertSame('ops.batch.updated', $event->broadcastAs());
    }

    public function test_broadcast_with_returns_batch_data(): void
    {
        $batch = ['id' => 5, 'type' => 'download', 'status' => 'completed'];
        $event = new OperationsBatchUpdated($batch);

        $this->assertSame(['batch' => $batch], $event->broadcastWith());
    }

    public function test_batch_property_is_accessible(): void
    {
        $batch = ['id' => 3, 'type' => 'upload'];
        $event = new OperationsBatchUpdated($batch);

        $this->assertSame($batch, $event->batch);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Operations\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OperationsBatchUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $batch
     */
    public function __construct(
        public readonly array $batch,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('operations')];
    }

    public function broadcastAs(): string
    {
        return 'ops.batch.updated';
    }

    /**
     * @return array{batch: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return ['batch' => $this->batch];
    }
}

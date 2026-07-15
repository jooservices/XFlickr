<?php

declare(strict_types=1);

namespace Modules\Operations\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OperationsOverviewChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $overview
     * @param  array<string, int|null>  $queues
     */
    public function __construct(
        public readonly array $overview,
        public readonly array $queues,
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
        return 'ops.overview.changed';
    }

    /**
     * @return array{overview: array<string, mixed>, queues: array<string, int|null>}
     */
    public function broadcastWith(): array
    {
        return [
            'overview' => $this->overview,
            'queues' => $this->queues,
        ];
    }
}

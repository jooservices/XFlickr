<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Modules\Operations\Events\OperationsBatchUpdated;
use Modules\Operations\Events\OperationsOverviewChanged;

final class OperationsBroadcastService
{
    private const int OVERVIEW_THROTTLE_SECONDS = 1;

    private const int BATCH_THROTTLE_SECONDS = 1;

    public function __construct(
        private readonly SnapshotService $snapshot,
        private readonly QueueDepthService $queues,
    ) {}

    /**
     * @param  array<string, mixed>  $batch
     */
    public function broadcastBatchUpdated(array $batch): void
    {
        $batchId = (int) ($batch['id'] ?? 0);
        if ($batchId < 1) {
            return;
        }

        if (! $this->acquireThrottle('ops:broadcast:batch:'.$batchId, self::BATCH_THROTTLE_SECONDS)) {
            return;
        }

        Event::dispatch(new OperationsBatchUpdated($batch));
        $this->broadcastOverviewChanged();
    }

    public function broadcastOverviewChanged(): void
    {
        if (! $this->acquireThrottle('ops:broadcast:overview', self::OVERVIEW_THROTTLE_SECONDS)) {
            return;
        }

        $payload = $this->snapshot->overviewAndQueues();
        $overview = is_array($payload['overview'] ?? null) ? $payload['overview'] : [];
        $queues = is_array($payload['queues'] ?? null) ? $payload['queues'] : $this->queues->depths();

        Event::dispatch(new OperationsOverviewChanged($overview, $queues));
    }

    private function acquireThrottle(string $key, int $seconds): bool
    {
        return Cache::add($key, 1, $seconds);
    }
}

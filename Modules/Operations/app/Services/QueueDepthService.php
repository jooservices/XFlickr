<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

final class QueueDepthService
{
    /** @var list<string> */
    public const QUEUE_NAMES = ['xflickr', 'xflickr-downloads', 'xflickr-uploads'];

    /**
     * @return array<string, int|null>
     */
    public function depths(): array
    {
        $depths = [];

        foreach (self::QUEUE_NAMES as $queue) {
            try {
                $depths[$queue] = Queue::size($queue);
            } catch (\Throwable $exception) {
                Log::warning('operations.queue_depth_failed', [
                    'queue' => $queue,
                    'message' => $exception->getMessage(),
                ]);
                $depths[$queue] = null;
            }
        }

        return $depths;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperationsStreamService
{
    private const int DEFAULT_MAX_STREAM_SECONDS = 60;

    private const int DEFAULT_POLL_INTERVAL_SECONDS = 5;

    public function __construct(
        private readonly OperationsSnapshotService $operations,
        private readonly int $maxStreamSeconds = self::DEFAULT_MAX_STREAM_SECONDS,
        private readonly int $pollIntervalSeconds = self::DEFAULT_POLL_INTERVAL_SECONDS,
    ) {}

    public function stream(): StreamedResponse
    {
        $maxStreamSeconds = $this->maxStreamSeconds;
        $pollIntervalSeconds = $this->pollIntervalSeconds;
        $operations = $this->operations;

        return response()->stream(function () use ($maxStreamSeconds, $pollIntervalSeconds, $operations): void {
            $startedAt = time();

            while (! connection_aborted() && (time() - $startedAt) < $maxStreamSeconds) {
                // SSE payload stays the raw snapshot; HTTP JSON endpoints use the controller envelope.
                $payload = json_encode($operations->snapshot(), JSON_THROW_ON_ERROR);

                echo "event: operations\n";
                echo 'data: '.$payload."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();

                if ($pollIntervalSeconds > 0) {
                    sleep($pollIntervalSeconds);
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}

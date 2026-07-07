<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Operations\OperationsSnapshotService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperationsStreamController
{
    private const int MAX_STREAM_SECONDS = 60;

    private const int POLL_INTERVAL_SECONDS = 5;

    public function __construct(
        private readonly OperationsSnapshotService $operations,
    ) {}

    public function stream(): StreamedResponse
    {
        return response()->stream(function (): void {
            $startedAt = time();

            while (! connection_aborted() && (time() - $startedAt) < self::MAX_STREAM_SECONDS) {
                $payload = json_encode($this->operations->snapshot(), JSON_THROW_ON_ERROR);

                echo "event: operations\n";
                echo 'data: '.$payload."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();

                sleep(self::POLL_INTERVAL_SECONDS);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}

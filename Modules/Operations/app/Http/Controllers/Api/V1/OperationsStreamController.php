<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers\Api\V1;

use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Operations\Services\OperationsSnapshotService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperationsStreamController extends BaseApiController
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
                // SSE payload stays the raw snapshot; HTTP JSON endpoints use the controller envelope.
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

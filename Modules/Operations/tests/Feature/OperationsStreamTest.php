<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Feature;

use Modules\Operations\Services\OperationsSnapshotService;
use Modules\Operations\Services\OperationsStreamService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class OperationsStreamTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_stream_returns_sse_event_with_operations_payload(): void
    {
        $this->createFlickrConnection();

        $this->app->instance(
            OperationsStreamService::class,
            new OperationsStreamService(
                app(OperationsSnapshotService::class),
                maxStreamSeconds: 1,
                pollIntervalSeconds: 0,
            ),
        );

        $response = $this->get('/api/v1/operations/stream');

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('event: operations', $body);
        $this->assertStringContainsString('"fetch_runs"', $body);
        $this->assertStringContainsString('"download_batches"', $body);
        $this->assertStringContainsString('"upload_batches"', $body);
    }
}

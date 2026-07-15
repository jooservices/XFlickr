<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Unit\Listeners;

use Illuminate\Support\Facades\Event;
use Modules\Operations\Events\OperationsBatchUpdated;
use Modules\Operations\Listeners\BroadcastOperationsBatchUpdated;
use Modules\Storage\Events\TransferBatchReconciled;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class BroadcastOperationsBatchUpdatedTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_listener_broadcasts_batch_updated_event(): void
    {
        $this->createFlickrConnection();

        Event::fake([OperationsBatchUpdated::class]);

        $event = new TransferBatchReconciled(
            batchId: 42,
            type: 'download',
            connectionKey: '12345678901@N01',
            subjectNsid: '98765432101@N01',
            status: 'running',
            totalCount: 100,
            completedCount: 50,
            failedCount: 2,
            sampleError: 'timeout',
            groupType: 'photoset',
            groupId: 'set-123',
            groupLabel: 'Vacation Photos',
            storageAccountId: 7,
            updatedAt: '2026-07-15T10:00:00+00:00',
        );

        $listener = app(BroadcastOperationsBatchUpdated::class);
        $listener->handle($event);

        Event::assertDispatched(OperationsBatchUpdated::class);
    }
}

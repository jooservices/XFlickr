<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Events;

use Modules\Transfer\Events\TransferBatchReconciled;
use Modules\Transfer\Tests\TestCase;

final class TransferBatchReconciledTest extends TestCase
{
    public function test_required_properties_are_accessible(): void
    {
        $batchId = fake()->numberBetween(1, 9999);
        $type = 'download';
        $connectionKey = fake()->uuid();
        $subjectNsid = fake()->numerify('########@N##');
        $status = 'completed';
        $totalCount = fake()->numberBetween(10, 100);
        $completedCount = fake()->numberBetween(5, 50);
        $failedCount = fake()->numberBetween(0, 5);
        $sampleError = 'HTTP 500';

        $event = new TransferBatchReconciled(
            batchId: $batchId,
            type: $type,
            connectionKey: $connectionKey,
            subjectNsid: $subjectNsid,
            status: $status,
            totalCount: $totalCount,
            completedCount: $completedCount,
            failedCount: $failedCount,
            sampleError: $sampleError,
        );

        $this->assertSame($batchId, $event->batchId);
        $this->assertSame($type, $event->type);
        $this->assertSame($connectionKey, $event->connectionKey);
        $this->assertSame($subjectNsid, $event->subjectNsid);
        $this->assertSame($status, $event->status);
        $this->assertSame($totalCount, $event->totalCount);
        $this->assertSame($completedCount, $event->completedCount);
        $this->assertSame($failedCount, $event->failedCount);
        $this->assertSame($sampleError, $event->sampleError);
    }

    public function test_optional_properties_default_to_null(): void
    {
        $event = new TransferBatchReconciled(
            batchId: 1,
            type: 'download',
            connectionKey: 'key-1',
            subjectNsid: null,
            status: 'pending',
            totalCount: 10,
            completedCount: 0,
            failedCount: 0,
            sampleError: null,
        );

        $this->assertNull($event->groupType);
        $this->assertNull($event->groupId);
        $this->assertNull($event->groupLabel);
        $this->assertNull($event->storageAccountId);
        $this->assertNull($event->updatedAt);
    }

    public function test_optional_properties_can_be_set(): void
    {
        $groupType = 'photoset';
        $groupId = fake()->uuid();
        $groupLabel = fake()->words(3, true);
        $storageAccountId = fake()->numberBetween(1, 100);
        $updatedAt = now()->toIso8601String();

        $event = new TransferBatchReconciled(
            batchId: 1,
            type: 'upload',
            connectionKey: 'key-2',
            subjectNsid: null,
            status: 'in_progress',
            totalCount: 20,
            completedCount: 10,
            failedCount: 2,
            sampleError: null,
            groupType: $groupType,
            groupId: $groupId,
            groupLabel: $groupLabel,
            storageAccountId: $storageAccountId,
            updatedAt: $updatedAt,
        );

        $this->assertSame($groupType, $event->groupType);
        $this->assertSame($groupId, $event->groupId);
        $this->assertSame($groupLabel, $event->groupLabel);
        $this->assertSame($storageAccountId, $event->storageAccountId);
        $this->assertSame($updatedAt, $event->updatedAt);
    }
}

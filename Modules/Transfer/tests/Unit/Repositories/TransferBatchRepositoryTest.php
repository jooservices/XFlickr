<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Repositories;

use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Repositories\TransferBatchRepository;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class TransferBatchRepositoryTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    private TransferBatchRepository $batches;

    protected function setUp(): void
    {
        parent::setUp();

        $this->batches = app(TransferBatchRepository::class);
    }

    public function test_find_lock_and_connection_key_lookup(): void
    {
        $connection = $this->createFlickrConnection();
        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $connection->connection_key,
            'status' => TransferBatchStatus::Running->value,
            'total_count' => 1,
        ]);

        $this->assertTrue($this->batches->findById($batch->id)?->is($batch));
        $this->assertSame($connection->connection_key, $this->batches->connectionKeyForId($batch->id));
        $this->assertNull($this->batches->connectionKeyForId(999999));
    }

    public function test_count_helpers_and_grouped_active_batches(): void
    {
        $first = $this->createFlickrConnection();
        $second = $this->createFlickrConnection();

        TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $first->connection_key,
            'subject_nsid' => $first->connection_key,
            'status' => TransferBatchStatus::Running->value,
            'total_count' => 2,
        ]);
        TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $second->connection_key,
            'subject_nsid' => $second->connection_key,
            'status' => TransferBatchStatus::Completed->value,
            'total_count' => 1,
        ]);

        $this->assertSame(1, $this->batches->countByTypeAndStatus('download', TransferBatchStatus::Running->value));
        $this->assertSame(1, $this->batches->countActiveForConnection($first->connection_key, 'download'));
        $this->assertSame(
            [$first->connection_key => 1],
            $this->batches->countActiveGroupedByConnection([$first->connection_key, $second->connection_key], 'download'),
        );
        $this->assertSame([], $this->batches->countActiveGroupedByConnection([], 'download'));
    }

    public function test_running_downloads_for_subjects_returns_empty_for_empty_input(): void
    {
        $connection = $this->createFlickrConnection();

        $this->assertTrue(
            $this->batches->runningDownloadsForSubjects($connection->connection_key, [])->isEmpty(),
        );
    }

    public function test_running_downloads_for_subjects_returns_matching_batches(): void
    {
        $connection = $this->createFlickrConnection();
        $subjectNsid = 'friend@N01';

        TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subjectNsid,
            'status' => TransferBatchStatus::Running->value,
            'total_count' => 3,
            'completed_count' => 1,
        ]);

        $rows = $this->batches->runningDownloadsForSubjects($connection->connection_key, [$subjectNsid]);

        $this->assertCount(1, $rows);
        $this->assertSame($subjectNsid, $rows->first()?->subject_nsid);
    }
}

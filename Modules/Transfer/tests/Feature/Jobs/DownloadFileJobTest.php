<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Feature\Jobs;

use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Jobs\DownloadFileJob;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Services\FileDownloadService;
use Modules\Transfer\Tests\TestCase;
use RuntimeException;

final class DownloadFileJobTest extends TestCase
{
    public function test_job_dispatches_on_correct_queue(): void
    {
        $job = new DownloadFileJob(
            sourceType: 'flickr_photo',
            sourceId: fake()->numerify('#########'),
            sourceOwner: fake()->numerify('########@N##'),
            connectionKey: fake()->uuid(),
        );

        $this->assertSame('xflickr-downloads', $job->queue);
    }

    public function test_retry_until_is_at_least_6_hours(): void
    {
        $job = new DownloadFileJob(
            sourceType: 'flickr_photo',
            sourceId: fake()->numerify('#########'),
            sourceOwner: fake()->numerify('########@N##'),
            connectionKey: fake()->uuid(),
        );

        $retryUntil = $job->retryUntil();

        $this->assertGreaterThan(now()->addHours(5)->timestamp, $retryUntil->getTimestamp());
    }

    public function test_max_exceptions_is_three(): void
    {
        $job = new DownloadFileJob(
            sourceType: 'flickr_photo',
            sourceId: fake()->numerify('#########'),
            sourceOwner: fake()->numerify('########@N##'),
            connectionKey: fake()->uuid(),
        );

        $this->assertSame(3, $job->maxExceptions);
    }

    public function test_handle_completes_existing_file_and_batch_item(): void
    {
        $batch = TransferBatch::factory()->create(['total_count' => 1]);
        StoredFile::factory()->create([
            'source_id' => 'photo-complete',
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
        ]);
        $item = TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => 'photo-complete',
        ]);
        $job = new DownloadFileJob('flickr_photo', 'photo-complete', 'owner@N01', 'connection-a', $batch->id);

        $job->handle(app(FileDownloadService::class));

        $this->assertSame(TransferItemStatus::Completed->value, $item->refresh()->status);
    }

    public function test_failed_marks_file_and_item_failed(): void
    {
        $batch = TransferBatch::factory()->create(['total_count' => 1]);
        $file = StoredFile::factory()->create([
            'source_id' => 'photo-failed',
            'variant' => 'original',
            'status' => StoredFileStatus::Pending->value,
        ]);
        $item = TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => 'photo-failed',
        ]);
        $job = new DownloadFileJob('flickr_photo', 'photo-failed', 'owner@N01', 'connection-a', $batch->id);

        $job->failed(new RuntimeException('terminal failure'));

        $this->assertSame(StoredFileStatus::Failed->value, $file->refresh()->status);
        $this->assertSame(TransferItemStatus::Failed->value, $item->refresh()->status);
    }
}

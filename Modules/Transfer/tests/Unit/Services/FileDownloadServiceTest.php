<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Crawler\Models\Photo;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Enums\TransferExecutionOutcome;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Services\FileDownloadService;
use Modules\Transfer\Tests\TestCase;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;

final class FileDownloadServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_execute_returns_completed_for_already_completed_stored_file(): void
    {
        Cache::flush();

        $sourceId = fake()->numerify('#########');
        $batch = TransferBatch::factory()->create(['total_count' => 1]);

        StoredFile::factory()->create([
            'source_type' => 'flickr_photo',
            'source_id' => $sourceId,
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
        ]);

        TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => $sourceId,
        ]);

        $service = app(FileDownloadService::class);
        $result = $service->execute('flickr_photo', $sourceId, 'owner@N00', 'conn-1', $batch->id);

        $this->assertSame(TransferExecutionOutcome::Completed, $result);
    }

    public function test_handle_failure_marks_file_and_item_failed(): void
    {
        $sourceId = fake()->numerify('#########');
        $batch = TransferBatch::factory()->create(['total_count' => 1]);

        StoredFile::factory()->create([
            'source_type' => 'flickr_photo',
            'source_id' => $sourceId,
            'variant' => 'original',
            'status' => StoredFileStatus::Pending->value,
        ]);

        TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => $sourceId,
        ]);

        $service = app(FileDownloadService::class);
        $service->handleFailure('flickr_photo', $sourceId, $batch->id, 'Connection timed out');

        $storedFile = StoredFile::query()
            ->where('source_id', $sourceId)
            ->first();

        $this->assertSame(StoredFileStatus::Failed->value, $storedFile->status);

        $item = TransferItem::query()
            ->where('transfer_batch_id', $batch->id)
            ->where('source_id', $sourceId)
            ->first();

        $this->assertSame(TransferItemStatus::Failed->value, $item->status);
    }

    public function test_execute_downloads_file_and_records_metadata(): void
    {
        Storage::fake();
        Cache::flush();
        $connection = $this->createFlickrConnection(['connection_key' => 'connection-a']);
        $sourceId = 'photo-download';
        Photo::query()->create([
            'flickr_photo_id' => $sourceId,
            'owner_nsid' => 'owner@N01',
            'raw_payload' => [
                'sizes' => [[
                    'label' => 'Original',
                    'source' => "https://example.test/{$sourceId}_o.jpg",
                    'width' => 2000,
                    'height' => 1000,
                ]],
            ],
        ]);
        $batch = TransferBatch::factory()->create(['total_count' => 1]);
        $item = TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => $sourceId,
        ]);
        $partPath = "flickr/{$connection->connection_key}/photos/{$sourceId}_jpg.jpg.part";
        Http::fake(function () use ($partPath) {
            Storage::put($partPath, 'downloaded image');

            return Http::response('downloaded image');
        });

        $outcome = app(FileDownloadService::class)->execute(
            'flickr_photo',
            $sourceId,
            'owner@N01',
            $connection->connection_key,
            $batch->id,
        );

        $stored = StoredFile::query()->where('source_id', $sourceId)->firstOrFail();
        $this->assertSame(TransferExecutionOutcome::Completed, $outcome);
        $this->assertSame(StoredFileStatus::Completed->value, $stored->status);
        $this->assertNotNull($stored->content_sha256);
        $this->assertSame(TransferItemStatus::Completed->value, $item->refresh()->status);
        Storage::assertExists((string) $stored->local_path);
    }

    public function test_execute_cleans_partial_file_and_keeps_item_processing_on_http_failure(): void
    {
        Storage::fake();
        Cache::flush();
        $connection = $this->createFlickrConnection(['connection_key' => 'connection-a']);
        $sourceId = 'photo-http-failure';
        Photo::query()->create([
            'flickr_photo_id' => $sourceId,
            'owner_nsid' => 'owner@N01',
            'raw_payload' => [
                'sizes' => [[
                    'label' => 'Original',
                    'source' => "https://example.test/{$sourceId}_o.jpg",
                ]],
            ],
        ]);
        $batch = TransferBatch::factory()->create(['total_count' => 1]);
        $item = TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'source_id' => $sourceId,
        ]);
        $partPath = "flickr/{$connection->connection_key}/photos/{$sourceId}_jpg.jpg.part";
        Http::fake(function () use ($partPath) {
            Storage::put($partPath, 'partial');

            return Http::response('failed', 503);
        });

        try {
            app(FileDownloadService::class)->execute(
                'flickr_photo',
                $sourceId,
                'owner@N01',
                $connection->connection_key,
                $batch->id,
            );
            $this->fail('Expected the failed download to throw.');
        } catch (Exception $exception) {
            $this->assertStringContainsString('503', $exception->getMessage());
        }

        Storage::assertMissing($partPath);
        $this->assertSame(TransferItemStatus::Processing->value, $item->refresh()->status);
        $this->assertDatabaseHas('stored_files', [
            'source_id' => $sourceId,
            'status' => StoredFileStatus::Pending->value,
        ]);
    }

    public function test_execute_defers_when_download_lock_is_held(): void
    {
        Cache::flush();
        $lock = Cache::lock('download_lock:flickr_photo:photo-locked', 120);
        $this->assertTrue($lock->get());

        try {
            $outcome = app(FileDownloadService::class)->execute(
                'flickr_photo',
                'photo-locked',
                'owner@N01',
                'connection-a',
                null,
            );
            $this->assertSame(TransferExecutionOutcome::Deferred, $outcome);
        } finally {
            $lock->release();
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PhotoTransferExecutionOutcome;
use App\Jobs\DownloadPhotoJob;
use App\Models\TransferBatch;
use App\Models\TransferItem;
use App\Services\Flickr\PhotoDownloadExecutionService;
use App\Services\Transfer\TransferBatchReconciler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use JOOservices\XFlickrCrawler\Models\Photo;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class DownloadPhotoJobTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_it_delegates_to_execution_service(): void
    {
        Storage::fake('local');
        Queue::fake();

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        Photo::query()->create([
            'flickr_photo_id' => 'photo-1',
            'owner_nsid' => 'friend@N01',
            'title' => 'Test',
            'secret' => 'abc123',
            'server' => '65535',
            'raw_payload' => [
                'sizes' => [
                    ['label' => 'Large', 'source' => 'https://example.test/photo-1.jpg'],
                ],
            ],
        ]);

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'friend@N01',
            'status' => 'running',
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => 'pending',
        ]);

        Http::fake([
            'https://example.test/*' => Http::response('jpeg-bytes', 200),
        ]);

        $job = new DownloadPhotoJob('photo-1', 'friend@N01', $connection->connection_key, $batch->id);
        $this->app->call([$job, 'handle']);

        Storage::disk('local')->assertExists('flickr/friend@N01/photos/photo-1_abc123.jpg');
        $this->assertSame('completed', $batch->fresh()->status);
    }

    public function test_it_downloads_after_queue_serialization_roundtrip(): void
    {
        Storage::fake('local');

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        Photo::query()->create([
            'flickr_photo_id' => 'photo-1',
            'owner_nsid' => 'friend@N01',
            'title' => 'Test',
            'secret' => 'abc123',
            'server' => '65535',
            'raw_payload' => [
                'sizes' => [
                    ['label' => 'Large', 'source' => 'https://example.test/photo-1.jpg'],
                ],
            ],
        ]);

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'friend@N01',
            'status' => 'running',
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => 'pending',
        ]);

        Http::fake([
            'https://example.test/*' => Http::response('jpeg-bytes', 200),
        ]);

        $job = new DownloadPhotoJob('photo-1', 'friend@N01', $connection->connection_key, $batch->id);
        /** @var DownloadPhotoJob $restored */
        $restored = unserialize(serialize($job));

        $this->app->call([$restored, 'handle']);

        Storage::disk('local')->assertExists('flickr/friend@N01/photos/photo-1_abc123.jpg');
        $this->assertSame('completed', $batch->fresh()->status);
    }

    public function test_reconciler_counts_each_photo_once_after_failure(): void
    {
        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'friend@N01',
            'status' => 'running',
            'total_count' => 2,
            'failed_count' => 6,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => 'failed',
            'error_message' => 'HTTP download failed with status: 403',
        ]);
        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-2',
            'status' => 'failed',
            'error_message' => 'HTTP download failed with status: 403',
        ]);

        app(TransferBatchReconciler::class)->reconcile($batch);

        $batch->refresh();
        $this->assertSame(2, $batch->failed_count);
        $this->assertSame(0, $batch->completed_count);
        $this->assertSame('failed', $batch->status);
    }

    public function test_it_releases_without_counting_deferrals_as_failures(): void
    {
        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        Photo::query()->create([
            'flickr_photo_id' => 'photo-1',
            'owner_nsid' => 'friend@N01',
            'title' => 'Test',
            'secret' => 'abc123',
            'server' => '65535',
            'raw_payload' => [],
        ]);

        $batch = TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'friend@N01',
            'status' => 'running',
            'total_count' => 1,
        ]);

        TransferItem::query()->create([
            'transfer_batch_id' => $batch->id,
            'flickr_photo_id' => 'photo-1',
            'status' => 'pending',
        ]);

        $lock = Cache::lock('download_lock:photo-1', 120);
        $lock->get();

        $outcome = app(PhotoDownloadExecutionService::class)->execute(
            'photo-1',
            'friend@N01',
            $connection->connection_key,
            $batch->id,
        );

        $this->assertSame(PhotoTransferExecutionOutcome::Deferred, $outcome);
        $this->assertSame('pending', TransferItem::query()->first()->status);
        $this->assertSame('running', $batch->fresh()->status);

        $lock->release();
    }

    public function test_it_has_extended_retry_window(): void
    {
        $job = new DownloadPhotoJob('photo-1', 'friend@N01', 'me@N01');

        $this->assertSame(100, $job->tries);
        $this->assertSame(3, $job->maxExceptions);
        $this->assertTrue($job->retryUntil() > now()->addHours(5));
    }
}

<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Services;

use App\Support\ThirdPartyApiLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use JOOservices\LaravelLogging\Jobs\StoreActivityLogJob;
use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Services\TransferObservability;
use Modules\Transfer\Tests\TestCase;

final class TransferObservabilityTest extends TestCase
{
    public function test_item_retry_queued_fingerprints_connection_key_and_correlates_batch(): void
    {
        Log::spy();
        Queue::fake();

        $connectionKey = 'secret-transfer-key-'.fake()->uuid();
        $batch = TransferBatch::factory()->create([
            'connection_key' => $connectionKey,
            'type' => 'download',
            'status' => TransferBatchStatus::Running->value,
            'total_count' => 3,
            'completed_count' => 2,
            'failed_count' => 1,
        ]);

        app(TransferObservability::class)->itemRetryQueued($batch, 'photo-retry');

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($batch, $connectionKey): bool {
                return $message === 'Transfer item queued for retry.'
                    && ($context['batch_id'] ?? null) === $batch->id
                    && ($context['source_id'] ?? null) === 'photo-retry'
                    && ($context['connection_key_fp'] ?? null) === ThirdPartyApiLogger::fingerprint($connectionKey)
                    && ! array_key_exists('connection_key', $context);
            });

        Queue::assertPushedOn('logging', StoreActivityLogJob::class, function (StoreActivityLogJob $job) use ($batch, $connectionKey): bool {
            $data = $job->data;

            return $data->action === 'transfer.item.retry_queued'
                && $data->correlationId === (string) $batch->id
                && ($data->properties['connection_key_fp'] ?? null) === ThirdPartyApiLogger::fingerprint($connectionKey)
                && ! array_key_exists('connection_key', $data->properties);
        });
    }

    public function test_batch_retries_queued_skips_zero_count(): void
    {
        Log::spy();
        Queue::fake();

        $batch = TransferBatch::factory()->create();

        app(TransferObservability::class)->batchRetriesQueued($batch, 0, 2);

        Log::shouldNotHaveReceived('info');
        Queue::assertNothingPushed();
    }
}

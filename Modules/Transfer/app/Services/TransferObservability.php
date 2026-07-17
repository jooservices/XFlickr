<?php

declare(strict_types=1);

namespace Modules\Transfer\Services;

use App\Support\ThirdPartyApiLogger;
use Illuminate\Support\Facades\Log;
use JOOservices\LaravelLogging\Facades\ActivityLog;
use Modules\Transfer\Models\TransferBatch;

/**
 * Durable domain trail for transfer retries and batch lifecycle.
 * Never logs raw connection keys or storage credentials.
 */
final class TransferObservability
{
    public function itemRetryQueued(TransferBatch $batch, string $sourceId): void
    {
        $context = [
            ...$this->batchContext($batch),
            'source_id' => $sourceId,
        ];

        Log::info('Transfer item queued for retry.', $context);

        $this->recordDomain('transfer.item.retry_queued', $batch, $context);
    }

    public function batchRetriesQueued(TransferBatch $batch, int $queuedCount, int $skippedCount): void
    {
        if ($queuedCount <= 0) {
            return;
        }

        $context = [
            ...$this->batchContext($batch),
            'queued_count' => $queuedCount,
            'skipped_count' => $skippedCount,
        ];

        Log::info('Transfer batch retries queued.', $context);

        $this->recordDomain('transfer.batch.retries_queued', $batch, $context);
    }

    public function batchStatusChanged(TransferBatch $batch, string $previousStatus, string $nextStatus): void
    {
        if ($previousStatus === $nextStatus) {
            return;
        }

        $context = [
            ...$this->batchContext($batch),
            'previous_status' => $previousStatus,
            'status' => $nextStatus,
        ];

        $level = in_array($nextStatus, ['failed', 'completed_with_errors'], true) ? 'warning' : 'info';
        $message = 'Transfer batch status changed.';

        if ($level === 'warning') {
            Log::warning($message, $context);
        } else {
            Log::info($message, $context);
        }

        $this->recordDomain('transfer.batch.status_changed', $batch, $context, $level);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function recordDomain(string $action, TransferBatch $batch, array $properties, string $level = 'info'): void
    {
        $batchId = (string) $batch->id;

        ActivityLog::domain()
            ->action($action)
            ->bySystem()
            ->level($level)
            ->correlationId($batchId)
            ->batchId($batchId)
            ->onExternal('transfer_batch', $batch->id)
            ->properties($properties)
            ->queue('logging')
            ->dispatch();
    }

    /**
     * @return array<string, mixed>
     */
    private function batchContext(TransferBatch $batch): array
    {
        return [
            'batch_id' => $batch->id,
            'type' => $batch->type,
            'connection_key_fp' => ThirdPartyApiLogger::fingerprint($batch->connection_key),
            'total_count' => $batch->total_count,
            'completed_count' => $batch->completed_count,
            'failed_count' => $batch->failed_count,
            'group_label' => $batch->group_label,
        ];
    }
}

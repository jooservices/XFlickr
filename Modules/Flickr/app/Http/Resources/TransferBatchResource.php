<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Storage\Models\TransferBatch;

/**
 * @mixin TransferBatch
 */
final class TransferBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TransferBatch|array<string, mixed> $batch */
        $batch = $this->resource;

        if (is_array($batch)) {
            return $batch;
        }

        return [
            'id' => $batch->id,
            'type' => $batch->type,
            'status' => $batch->status,
            'total_count' => $batch->total_count,
            'completed_count' => $batch->completed_count,
            'failed_count' => $batch->failed_count,
            'connection_key' => $batch->connection_key,
            'subject_nsid' => $batch->subject_nsid,
            'group_type' => $batch->group_type,
            'group_id' => $batch->group_id,
            'group_label' => $batch->group_label,
            'storage_account_id' => $batch->storage_account_id,
            'created_at' => $batch->created_at?->toIso8601String(),
            'updated_at' => $batch->updated_at?->toIso8601String(),
            'sample_error' => $batch->getAttribute('sample_error'),
        ];
    }
}

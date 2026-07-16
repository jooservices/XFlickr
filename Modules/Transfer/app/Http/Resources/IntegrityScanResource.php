<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class IntegrityScanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->uuid, 'status' => $this->status->value, 'orphaned_count' => $this->orphaned_count, 'missing_count' => $this->missing_count, 'started_at' => $this->started_at?->toIso8601String(), 'finished_at' => $this->finished_at?->toIso8601String(), 'error_message' => $this->error_message];
    }
}

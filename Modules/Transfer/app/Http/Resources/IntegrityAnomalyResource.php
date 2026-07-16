<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class IntegrityAnomalyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->uuid, 'type' => $this->type->value, 'source_id' => $this->source_id, 'connection_key' => $this->connection_key, 'metadata' => $this->metadata, 'created_at' => $this->created_at?->toIso8601String()];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{batch: array<string, mixed>, items: mixed} $resource
 */
final class TransferBatchDetailResource extends JsonResource
{
    /**
     * @return array{batch: array<string, mixed>, items: mixed}
     */
    public function toArray(Request $request): array
    {
        /** @var array{batch: array<string, mixed>, items: mixed} $payload */
        $payload = $this->resource;

        return [
            'batch' => TransferBatchResource::make($payload['batch'])->resolve(),
            'items' => $payload['items'],
        ];
    }
}

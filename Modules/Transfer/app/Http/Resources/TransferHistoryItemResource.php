<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TransferHistoryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $item */
        $item = $this->resource;

        return $item;
    }
}

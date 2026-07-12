<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Provider-normalized album or item row from browse/sync.
 *
 * @property-read array<string, mixed> $resource
 */
final class StorageRemoteNodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $node */
        $node = $this->resource;

        return $node;
    }
}

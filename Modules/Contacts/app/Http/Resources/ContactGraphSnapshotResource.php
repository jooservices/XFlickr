<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array<string, mixed> $resource
 */
final class ContactGraphSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = $this->resource;

        return [
            'root_nsid' => $snapshot['root_nsid'] ?? null,
            'nodes' => array_values($snapshot['nodes'] ?? []),
            'edges' => array_values($snapshot['edges'] ?? []),
            'meta' => $snapshot['meta'] ?? [],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array<string, mixed> $resource
 */
final class ContactGraphDeltaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $delta */
        $delta = $this->resource;

        return [
            'edges' => array_values($delta['edges'] ?? []),
            'nodes' => array_values($delta['nodes'] ?? []),
            'max_edge_id' => $delta['max_edge_id'] ?? null,
            'done' => (bool) ($delta['done'] ?? true),
            'crawl_status' => $delta['crawl_status'] ?? null,
        ];
    }
}

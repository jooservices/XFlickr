<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array<string, mixed> $resource
 */
final class StorageSyncResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->resource;

        return [
            'albums_synced' => (int) ($result['albums_synced'] ?? 0),
            'items_synced' => (int) ($result['items_synced'] ?? 0),
            'has_more' => (bool) ($result['has_more'] ?? false),
            'last_synced_at' => $result['last_synced_at'] ?? null,
            'albums_complete' => (bool) ($result['albums_complete'] ?? false),
            'items_complete' => (bool) ($result['items_complete'] ?? false),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{albums?: list<array<string, mixed>>, items?: list<array<string, mixed>>} $resource
 */
final class StorageBrowseResource extends JsonResource
{
    /**
     * @return array{albums: list<array<string, mixed>>, items: list<array<string, mixed>>}
     */
    public function toArray(Request $request): array
    {
        /** @var array{albums?: list<array<string, mixed>>, items?: list<array<string, mixed>>} $payload */
        $payload = $this->resource;

        return [
            'albums' => StorageRemoteNodeResource::collection($payload['albums'] ?? [])->resolve(),
            'items' => StorageRemoteNodeResource::collection($payload['items'] ?? [])->resolve(),
        ];
    }
}

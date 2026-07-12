<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array<string, mixed> $resource
 */
final class StorageReauthorizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->resource;

        return [
            'needs_reauthorization' => (bool) ($payload['needs_reauthorization'] ?? true),
            'reauthorize_url' => $payload['reauthorize_url'] ?? null,
            'missing_scopes' => $payload['missing_scopes'] ?? [],
        ];
    }
}

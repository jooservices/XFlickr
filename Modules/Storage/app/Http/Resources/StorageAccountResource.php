<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array<string, mixed> $resource
 */
final class StorageAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $account */
        $account = $this->resource;

        return [
            'id' => $account['id'] ?? null,
            'provider' => $account['provider'] ?? null,
            'label' => $account['label'] ?? null,
            'is_default' => (bool) ($account['is_default'] ?? false),
            'connected_at' => $account['connected_at'] ?? null,
            'needs_reauthorization' => (bool) ($account['needs_reauthorization'] ?? false),
            'missing_scopes' => $account['missing_scopes'] ?? [],
            'reauthorize_url' => $account['reauthorize_url'] ?? null,
            'connection_meta' => $account['connection_meta'] ?? null,
        ];
    }
}

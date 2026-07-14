<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{photos?: list<array<string, mixed>>} $resource
 */
final class PhotoDownloadProgressResource extends JsonResource
{
    /**
     * @return array{photos: list<array<string, mixed>>}
     */
    public function toArray(Request $request): array
    {
        /** @var array{photos?: list<array<string, mixed>>} $payload */
        $payload = $this->resource;

        return [
            'photos' => array_values($payload['photos'] ?? []),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array<string, mixed> $resource
 */
final class DashboardSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = $this->resource;

        return [
            'generated_at' => $snapshot['generated_at'] ?? null,
            'global' => $snapshot['global'] ?? [],
            'accounts' => $snapshot['accounts'] ?? [],
            'databases' => $snapshot['databases'] ?? [
                'mysql' => [],
                'mongodb' => [],
                'history' => [],
            ],
            'alerts' => $snapshot['alerts'] ?? [],
        ];
    }
}

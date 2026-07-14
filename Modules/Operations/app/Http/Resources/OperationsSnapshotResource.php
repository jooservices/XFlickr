<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array<string, mixed> $resource
 */
final class OperationsSnapshotResource extends JsonResource
{
    /**
     * @return array{
     *     overview: array<string, mixed>,
     *     dependencies: array<string, mixed>,
     *     databases: array<string, mixed>,
     *     accounts: list<mixed>,
     *     fetch_runs: list<mixed>,
     *     download_batches: list<mixed>,
     *     upload_batches: list<mixed>
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = $this->resource;

        return [
            'overview' => is_array($snapshot['overview'] ?? null) ? $snapshot['overview'] : [],
            'dependencies' => is_array($snapshot['dependencies'] ?? null) ? $snapshot['dependencies'] : [],
            'databases' => is_array($snapshot['databases'] ?? null) ? $snapshot['databases'] : [],
            'accounts' => array_values(is_array($snapshot['accounts'] ?? null) ? $snapshot['accounts'] : []),
            'fetch_runs' => array_values($snapshot['fetch_runs'] ?? []),
            'download_batches' => array_values($snapshot['download_batches'] ?? []),
            'upload_batches' => array_values($snapshot['upload_batches'] ?? []),
        ];
    }
}

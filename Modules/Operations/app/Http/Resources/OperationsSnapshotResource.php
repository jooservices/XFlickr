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
            'fetch_runs' => array_values($snapshot['fetch_runs'] ?? []),
            'download_batches' => array_values($snapshot['download_batches'] ?? []),
            'upload_batches' => array_values($snapshot['upload_batches'] ?? []),
        ];
    }
}

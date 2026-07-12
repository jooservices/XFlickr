<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{deleted?: list<string>, failed?: list<array{id: string, message: string}>} $resource
 */
final class StorageDeleteResultResource extends JsonResource
{
    /**
     * @return array{deleted: list<string>, failed: list<array{id: string, message: string}>}
     */
    public function toArray(Request $request): array
    {
        /** @var array{deleted?: list<string>, failed?: list<array{id: string, message: string}>} $result */
        $result = $this->resource;

        return [
            'deleted' => array_values($result['deleted'] ?? []),
            'failed' => array_values($result['failed'] ?? []),
        ];
    }
}

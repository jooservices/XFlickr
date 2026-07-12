<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array<string, mixed> $resource
 */
final class ContactGraphExpandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->resource;

        return [
            'subject_nsid' => $result['subject_nsid'] ?? null,
            'reexpand' => (bool) ($result['reexpand'] ?? false),
            'crawl_run_id' => $result['crawl_run_id'] ?? null,
            'status' => $result['status'] ?? null,
        ];
    }
}

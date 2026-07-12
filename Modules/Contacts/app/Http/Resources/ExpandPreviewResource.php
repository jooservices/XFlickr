<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array<string, mixed> $resource
 */
final class ExpandPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->resource;

        return [
            'account' => $payload['account'] ?? null,
            'saved_contacts_count' => (int) ($payload['saved_contacts_count'] ?? 0),
            'spider' => $payload['spider'] ?? [],
            'full_pass' => $payload['full_pass'] ?? [],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{contacts?: list<array<string, mixed>>} $resource
 */
final class ContactProgressResource extends JsonResource
{
    /**
     * @return array{contacts: list<array<string, mixed>>}
     */
    public function toArray(Request $request): array
    {
        /** @var array{contacts?: list<array<string, mixed>>} $payload */
        $payload = $this->resource;

        return [
            'contacts' => array_values($payload['contacts'] ?? []),
        ];
    }
}

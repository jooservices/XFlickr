<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ContactImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($this->resource) ? $this->resource : [];

        return [
            'nsid' => (string) ($payload['nsid'] ?? ''),
            'username' => isset($payload['username']) && is_string($payload['username']) ? $payload['username'] : null,
            'realname' => isset($payload['realname']) && is_string($payload['realname']) ? $payload['realname'] : null,
            'already_linked' => (bool) ($payload['already_linked'] ?? false),
            'crawl_started' => (bool) ($payload['crawl_started'] ?? false),
            'redirect_path' => (string) ($payload['redirect_path'] ?? ''),
        ];
    }
}

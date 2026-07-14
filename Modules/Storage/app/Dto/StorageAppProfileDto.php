<?php

declare(strict_types=1);

namespace Modules\Storage\Dto;

use JOOservices\Dto\Core\Data;

final class StorageAppProfileDto extends Data
{
    public function __construct(
        public string $provider,
        public string $clientId,
        public string $clientSecret,
        public ?string $label = null,
        public ?string $redirect = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function transformInput(array $data): array
    {
        $label = $data['label'] ?? null;
        $redirect = $data['redirect'] ?? $data['redirect_uri'] ?? $data['redirectUri'] ?? null;

        return [
            'provider' => (string) ($data['provider'] ?? ''),
            'clientId' => (string) ($data['client_id'] ?? $data['clientId'] ?? ''),
            'clientSecret' => (string) ($data['client_secret'] ?? $data['clientSecret'] ?? ''),
            'label' => $label !== null && $label !== '' ? (string) $label : null,
            'redirect' => $redirect !== null && $redirect !== '' ? (string) $redirect : null,
        ];
    }
}

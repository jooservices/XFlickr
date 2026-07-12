<?php

declare(strict_types=1);

namespace Modules\Settings\Dto;

use JOOservices\Dto\Core\Data;

final class OAuthAppConfigDto extends Data
{
    public function __construct(
        public string $clientId,
        public string $clientSecret,
        public ?string $redirectUri = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function transformInput(array $data): array
    {
        return [
            'clientId' => (string) ($data['client_id'] ?? $data['clientId'] ?? ''),
            'clientSecret' => (string) ($data['client_secret'] ?? $data['clientSecret'] ?? ''),
            'redirectUri' => isset($data['redirect_uri']) || isset($data['redirectUri'])
                ? (string) ($data['redirect_uri'] ?? $data['redirectUri'])
                : null,
        ];
    }
}

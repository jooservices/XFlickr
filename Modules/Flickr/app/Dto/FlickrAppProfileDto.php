<?php

declare(strict_types=1);

namespace Modules\Flickr\Dto;

use JOOservices\Dto\Core\Data;

final class FlickrAppProfileDto extends Data
{
    public function __construct(
        public string $apiKey,
        public string $apiSecret,
        public string $profile = 'main',
        public ?string $label = null,
        public ?string $callbackUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function transformInput(array $data): array
    {
        $label = $data['label'] ?? null;
        $callbackUrl = $data['callback_url'] ?? $data['callbackUrl'] ?? null;

        return [
            'apiKey' => (string) ($data['api_key'] ?? $data['apiKey'] ?? ''),
            'apiSecret' => (string) ($data['api_secret'] ?? $data['apiSecret'] ?? ''),
            'profile' => (string) ($data['profile'] ?? 'main'),
            'label' => $label !== null && $label !== '' ? (string) $label : null,
            'callbackUrl' => $callbackUrl !== null && $callbackUrl !== '' ? (string) $callbackUrl : null,
        ];
    }
}

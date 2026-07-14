<?php

declare(strict_types=1);

namespace Modules\Crawler\DTO;

use JOOservices\Dto\Core\Dto;
use JOOservices\Dto\Exceptions\HydrationException;

final class FlickrTokenPayload extends Dto
{
    public function __construct(
        public readonly string $oauthToken,
        public readonly string $oauthTokenSecret,
        public readonly ?string $userNsid = null,
        public readonly ?string $username = null,
        public readonly ?string $fullname = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function transformInput(array $data): array
    {
        if (! array_key_exists('oauthToken', $data) && array_key_exists('oauth_token', $data)) {
            $data['oauthToken'] = $data['oauth_token'];
        }

        if (! array_key_exists('oauthTokenSecret', $data) && array_key_exists('oauth_token_secret', $data)) {
            $data['oauthTokenSecret'] = $data['oauth_token_secret'];
        }

        if (! array_key_exists('userNsid', $data) && array_key_exists('user_nsid', $data)) {
            $data['userNsid'] = $data['user_nsid'];
        }

        return $data;
    }

    protected function afterHydration(): void
    {
        if ($this->oauthToken === '' || $this->oauthTokenSecret === '') {
            throw new HydrationException(
                message: 'Flickr token payload must include oauthToken and oauthTokenSecret.',
            );
        }
    }
}

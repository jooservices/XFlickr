<?php

declare(strict_types=1);

namespace Modules\Storage\Services\OAuth;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

final class MicrosoftProvider extends AbstractProvider implements ProviderInterface
{
    protected $scopeSeparator = ' ';

    protected $scopes = [
        'User.Read',
    ];

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase('https://login.microsoftonline.com/common/oauth2/v2.0/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get('https://graph.microsoft.com/v1.0/me', [
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
            RequestOptions::QUERY => [
                '$select' => 'id,displayName,userPrincipalName,mail',
            ],
        ]);

        return (array) json_decode((string) $response->getBody(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => Arr::get($user, 'id'),
            'nickname' => Arr::get($user, 'userPrincipalName'),
            'name' => Arr::get($user, 'displayName'),
            'email' => Arr::get($user, 'mail') ?: Arr::get($user, 'userPrincipalName'),
            'avatar' => null,
        ]);
    }
}

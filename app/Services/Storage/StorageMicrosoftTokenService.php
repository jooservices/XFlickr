<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class StorageMicrosoftTokenService
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function accessToken(array $credentials): string
    {
        $accessToken = (string) ($credentials['access_token'] ?? '');
        $expiresAt = isset($credentials['expires_at']) ? strtotime((string) $credentials['expires_at']) : false;

        if ($accessToken !== '' && ($expiresAt === false || $expiresAt > time() + 60)) {
            return $accessToken;
        }

        $refreshToken = (string) ($credentials['refresh_token'] ?? '');
        $clientId = (string) ($credentials['client_id'] ?? '');
        $clientSecret = (string) ($credentials['client_secret'] ?? '');

        if ($refreshToken === '' || $clientId === '' || $clientSecret === '') {
            throw new RuntimeException('OneDrive credentials are incomplete for token refresh.');
        }

        $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('OneDrive token refresh failed.');
        }

        return (string) $response->json('access_token');
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Google\Client as GoogleClient;
use RuntimeException;

final class StorageGoogleTokenService
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function client(array $credentials): GoogleClient
    {
        $clientId = (string) ($credentials['client_id'] ?? '');
        $clientSecret = (string) ($credentials['client_secret'] ?? '');
        $refreshToken = (string) ($credentials['refresh_token'] ?? '');

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new RuntimeException('Google credentials are incomplete.');
        }

        $client = new GoogleClient;
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->refreshToken($refreshToken);

        return $client;
    }

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

        $client = $this->client($credentials);
        $token = $client->getAccessToken();

        if (! is_array($token) || empty($token['access_token'])) {
            throw new RuntimeException('Google token refresh failed.');
        }

        return (string) $token['access_token'];
    }
}

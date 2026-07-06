<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\StorageAccount;
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
    public function clientForAccount(array $credentials, StorageAccount $account): GoogleClient
    {
        $accessToken = $this->accessToken($credentials, $account);
        $client = $this->client($account->credentials ?? $credentials);
        $client->setAccessToken(['access_token' => $accessToken]);

        return $client;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function accessToken(array $credentials, StorageAccount $account): string
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

        $account->update([
            'credentials' => $this->refreshedCredentials($credentials, $token),
        ]);

        return (string) $token['access_token'];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    private function refreshedCredentials(array $credentials, array $token): array
    {
        $refreshed = array_merge($credentials, [
            'access_token' => $token['access_token'],
            'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600))->toIso8601String(),
        ]);

        if (! empty($token['refresh_token'])) {
            $refreshed['refresh_token'] = $token['refresh_token'];
        }

        if (! empty($token['token_type'])) {
            $refreshed['token_type'] = $token['token_type'];
        }

        return $refreshed;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\StorageAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class StorageMicrosoftTokenService
{
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

        $token = $response->json();
        if (! is_array($token) || empty($token['access_token'])) {
            throw new RuntimeException('OneDrive token refresh failed.');
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

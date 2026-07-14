<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\Tokens\MicrosoftTokenService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageTokenRefreshTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_microsoft_token_refresh_persists_updated_credentials(): void
    {
        Http::fake([
            'login.microsoftonline.com/common/oauth2/v2.0/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 7200,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $account = StorageAccount::query()->create([
            'provider' => 'onedrive',
            'label' => 'OneDrive',
            'credentials' => [
                'access_token' => 'expired-access-token',
                'refresh_token' => 'old-refresh-token',
                'client_id' => 'client-id',
                'client_secret' => 'client-secret',
                'expires_at' => now()->subMinute()->toIso8601String(),
                'granted_scopes' => ['Files.ReadWrite'],
            ],
            'connected_at' => now(),
        ]);

        $accessToken = app(MicrosoftTokenService::class)->accessToken($account->credentials ?? [], $account);

        $this->assertSame('new-access-token', $accessToken);

        $credentials = $account->refresh()->credentials;
        $this->assertSame('new-access-token', $credentials['access_token'] ?? null);
        $this->assertSame('new-refresh-token', $credentials['refresh_token'] ?? null);
        $this->assertSame('client-id', $credentials['client_id'] ?? null);
        $this->assertSame('Files.ReadWrite', $credentials['granted_scopes'][0] ?? null);
        $this->assertNotSame('expired-access-token', $credentials['access_token'] ?? null);
    }
}

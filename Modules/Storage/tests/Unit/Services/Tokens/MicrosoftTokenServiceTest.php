<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services\Tokens;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\Tokens\MicrosoftTokenService;
use RuntimeException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class MicrosoftTokenServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_access_token_returns_valid_cached_token_without_refresh(): void
    {
        $token = fake()->sha256();
        $account = StorageAccount::factory()->oneDrive()->create([
            'credentials' => [
                'access_token' => $token,
                'refresh_token' => fake()->sha256(),
                'client_id' => fake()->uuid(),
                'client_secret' => fake()->sha256(),
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
        ]);

        $accessToken = app(MicrosoftTokenService::class)->accessToken($account->credentials ?? [], $account);

        $this->assertSame($token, $accessToken);
        Http::assertNothingSent();
    }

    public function test_access_token_rejects_incomplete_refresh_credentials(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create([
            'credentials' => [
                'access_token' => '',
                'refresh_token' => '',
                'client_id' => 'client-id',
                'client_secret' => 'client-secret',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OneDrive credentials are incomplete for token refresh.');

        app(MicrosoftTokenService::class)->accessToken($account->credentials ?? [], $account);
    }

    public function test_access_token_throws_when_refresh_http_fails(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create([
            'credentials' => [
                'access_token' => 'expired',
                'refresh_token' => 'refresh-token',
                'client_id' => 'client-id',
                'client_secret' => 'client-secret',
                'expires_at' => now()->subMinute()->toIso8601String(),
            ],
        ]);

        Http::fake([
            'login.microsoftonline.com/common/oauth2/v2.0/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OneDrive token refresh failed.');

        app(MicrosoftTokenService::class)->accessToken($account->credentials ?? [], $account);
    }

    public function test_access_token_throws_when_refresh_payload_missing_access_token(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create([
            'credentials' => [
                'access_token' => 'expired',
                'refresh_token' => 'refresh-token',
                'client_id' => 'client-id',
                'client_secret' => 'client-secret',
                'expires_at' => now()->subMinute()->toIso8601String(),
            ],
        ]);

        Http::fake([
            'login.microsoftonline.com/common/oauth2/v2.0/token' => Http::response(['token_type' => 'Bearer'], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OneDrive token refresh failed.');

        app(MicrosoftTokenService::class)->accessToken($account->credentials ?? [], $account);
    }
}

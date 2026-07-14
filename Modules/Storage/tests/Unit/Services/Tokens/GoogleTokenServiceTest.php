<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services\Tokens;

use Google\Client as GoogleClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Repositories\StorageAccountRepository;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use RuntimeException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class GoogleTokenServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_client_rejects_incomplete_credentials(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google credentials are incomplete.');

        app(GoogleTokenService::class)->client([
            'client_id' => 'client-id',
        ]);
    }

    public function test_access_token_returns_valid_cached_token_without_refresh(): void
    {
        $token = fake()->sha256();
        $account = StorageAccount::factory()->googleDrive()->create([
            'credentials' => [
                'access_token' => $token,
                'refresh_token' => fake()->sha256(),
                'client_id' => fake()->uuid(),
                'client_secret' => fake()->sha256(),
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
        ]);

        $accessToken = app(GoogleTokenService::class)->accessToken($account->credentials ?? [], $account);

        $this->assertSame($token, $accessToken);
    }

    public function test_access_token_refreshes_expired_credentials_and_persists(): void
    {
        $newToken = fake()->sha256();
        $account = StorageAccount::factory()->googleDrive()->create([
            'credentials' => [
                'access_token' => 'expired-token',
                'refresh_token' => 'refresh-token',
                'client_id' => 'client-id',
                'client_secret' => 'client-secret',
                'expires_at' => now()->subMinute()->toIso8601String(),
            ],
        ]);

        $googleClient = $this->googleClientWithResponses([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'access_token' => $newToken,
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'refresh_token' => 'rotated-refresh',
            ], JSON_THROW_ON_ERROR)),
        ]);
        $googleClient->fetchAccessTokenWithRefreshToken('refresh-token');

        $service = Mockery::mock(GoogleTokenService::class, [app(StorageAccountRepository::class)])->makePartial();
        $service->shouldReceive('client')->once()->andReturn($googleClient);

        $accessToken = $service->accessToken($account->credentials ?? [], $account);

        $this->assertSame($newToken, $accessToken);
        $credentials = $account->refresh()->credentials;
        $this->assertSame($newToken, $credentials['access_token'] ?? null);
        $this->assertSame('rotated-refresh', $credentials['refresh_token'] ?? null);
        $this->assertSame('Bearer', $credentials['token_type'] ?? null);
    }

    public function test_access_token_throws_when_refresh_returns_invalid_payload(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create([
            'credentials' => [
                'access_token' => '',
                'refresh_token' => 'refresh-token',
                'client_id' => 'client-id',
                'client_secret' => 'client-secret',
            ],
        ]);

        $googleClient = $this->googleClientWithResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{}'),
        ]);

        $service = Mockery::mock(GoogleTokenService::class, [app(StorageAccountRepository::class)])->makePartial();
        $service->shouldReceive('client')->once()->andReturn($googleClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google token refresh failed.');

        $service->accessToken($account->credentials ?? [], $account);
    }

    public function test_client_for_account_sets_access_token_on_client(): void
    {
        $token = fake()->sha256();
        $account = StorageAccount::factory()->googleDrive()->create([
            'credentials' => [
                'access_token' => $token,
                'refresh_token' => fake()->sha256(),
                'client_id' => fake()->uuid(),
                'client_secret' => fake()->sha256(),
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
        ]);

        $service = Mockery::mock(GoogleTokenService::class, [app(StorageAccountRepository::class)])->makePartial();
        $service->shouldReceive('accessToken')->once()->andReturn($token);
        $service->shouldReceive('client')->once()->andReturn(new GoogleClient);

        $client = $service->clientForAccount($account->credentials ?? [], $account);

        $this->assertSame($token, $client->getAccessToken()['access_token'] ?? null);
    }

    /**
     * @param  list<Response>  $responses
     */
    private function googleClientWithResponses(array $responses): GoogleClient
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        $client = new GoogleClient;
        $client->setClientId('client-id');
        $client->setClientSecret('client-secret');
        $client->setHttpClient(new GuzzleClient(['handler' => $handler]));

        return $client;
    }
}

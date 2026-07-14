<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Illuminate\Support\Facades\Event;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Events\StorageAccountConnected;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageOAuthService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageOAuthServiceCompleteTest extends TestCase
{
    use SafeRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedStorageAppCredentials(StorageDriver::GooglePhotos);
    }

    public function test_complete_connects_new_account_from_social_user(): void
    {
        Event::fake([StorageAccountConnected::class]);

        $email = fake()->safeEmail();
        $this->bindSocialiteUser(
            driver: StorageDriver::GooglePhotos,
            token: 'new-access-token',
            refreshToken: 'new-refresh-token',
            email: $email,
            name: 'Photos User',
        );

        session([
            'storage_oauth_state' => 'valid-state',
            'storage_oauth_provider' => StorageDriver::GooglePhotos->value,
        ]);

        $account = app(StorageOAuthService::class)->complete(
            StorageDriver::GooglePhotos->value,
            'auth-code',
        );

        $this->assertSame(StorageDriver::GooglePhotos->value, $account->provider);
        $this->assertSame('Photos User', $account->label);
        $this->assertSame('new-access-token', $account->credentials['access_token'] ?? null);
        $this->assertNull(session('storage_oauth_state'));
        Event::assertDispatched(StorageAccountConnected::class);
    }

    public function test_complete_reauthorizes_existing_account_when_session_account_id_matches(): void
    {
        $existing = StorageAccount::factory()->googlePhotos()->create([
            'label' => 'existing@example.com',
        ]);

        $this->bindSocialiteUser(
            driver: StorageDriver::GooglePhotos,
            token: 'rotated-access-token',
            refreshToken: 'rotated-refresh-token',
            email: 'existing@example.com',
            name: 'Existing Photos',
        );

        session([
            'storage_oauth_state' => 'valid-state',
            'storage_oauth_provider' => StorageDriver::GooglePhotos->value,
            'storage_oauth_account_id' => $existing->id,
        ]);

        $account = app(StorageOAuthService::class)->complete(
            StorageDriver::GooglePhotos->value,
            'auth-code',
        );

        $this->assertSame($existing->id, $account->id);
        $this->assertSame('rotated-access-token', $account->credentials['access_token'] ?? null);
    }

    public function test_validate_state_rejects_mismatched_values(): void
    {
        session(['storage_oauth_state' => 'expected-state']);

        $service = app(StorageOAuthService::class);

        $this->assertTrue($service->validateState('expected-state'));
        $this->assertFalse($service->validateState('wrong-state'));
        $this->assertFalse($service->validateState(null));
    }

    public function test_complete_connects_new_account_when_session_account_id_provider_mismatches(): void
    {
        Event::fake([StorageAccountConnected::class]);

        $existing = StorageAccount::factory()->oneDrive()->create();

        $this->bindSocialiteUser(
            driver: StorageDriver::GooglePhotos,
            token: 'fresh-access-token',
            refreshToken: 'fresh-refresh-token',
            email: fake()->safeEmail(),
            name: 'Fresh Photos',
        );

        session([
            'storage_oauth_state' => 'valid-state',
            'storage_oauth_provider' => StorageDriver::GooglePhotos->value,
            'storage_oauth_account_id' => $existing->id,
        ]);

        $account = app(StorageOAuthService::class)->complete(
            StorageDriver::GooglePhotos->value,
            'auth-code',
        );

        $this->assertNotSame($existing->id, $account->id);
        $this->assertSame(StorageDriver::GooglePhotos->value, $account->provider);
        Event::assertDispatched(StorageAccountConnected::class);
    }

    private function seedStorageAppCredentials(StorageDriver $driver): void
    {
        RuntimeConfig::set("storage_app.{$driver->value}", [
            'client_id' => fake()->uuid(),
            'client_secret' => fake()->sha256(),
            'redirect' => "http://localhost/storage/callback/{$driver->value}",
        ], 'json');
        RuntimeConfig::refresh();
    }

    private function bindSocialiteUser(
        StorageDriver $driver,
        string $token,
        ?string $refreshToken,
        string $email,
        string $name,
    ): void {
        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->token = $token;
        $socialUser->refreshToken = $refreshToken;
        $socialUser->expiresIn = 3600;
        $socialUser->shouldReceive('getEmail')->andReturn($email);
        $socialUser->shouldReceive('getId')->andReturn(fake()->uuid());
        $socialUser->shouldReceive('getName')->andReturn($name);

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialUser);

        $socialite = Mockery::mock(SocialiteManager::class);
        $socialite->shouldReceive('buildProvider')
            ->once()
            ->andReturn($provider);

        $this->app->instance(SocialiteFactory::class, $socialite);
    }
}

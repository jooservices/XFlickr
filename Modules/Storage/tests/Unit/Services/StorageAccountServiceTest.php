<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Illuminate\Support\Facades\Event;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Events\StorageAccountConnected;
use Modules\Storage\Events\StorageAccountDisconnected;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageAccountService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageAccountServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_connect_creates_default_account_and_dispatches_event(): void
    {
        Event::fake([StorageAccountConnected::class]);

        $account = app(StorageAccountService::class)->connect(
            StorageDriver::GooglePhotos->value,
            'photos@example.com',
            [
                'access_token' => fake()->sha256(),
                'refresh_token' => fake()->sha256(),
                'expires_in' => 3600,
                'granted_scopes' => StorageDriver::GooglePhotos->defaultScopes(),
            ],
            'Primary Photos',
        );

        $this->assertTrue($account->is_default);
        $this->assertSame('Primary Photos', $account->label);
        $this->assertNotEmpty($account->credentials['expires_at'] ?? null);

        Event::assertDispatched(StorageAccountConnected::class, function (StorageAccountConnected $event) use ($account): bool {
            return $event->accountId === $account->id
                && $event->provider === StorageDriver::GooglePhotos->value;
        });
    }

    public function test_connect_does_not_override_existing_default(): void
    {
        StorageAccount::factory()->googlePhotos()->default()->create();

        $second = app(StorageAccountService::class)->connect(
            StorageDriver::GooglePhotos->value,
            'second@example.com',
            ['access_token' => fake()->sha256()],
            'Second Photos',
        );

        $this->assertFalse($second->is_default);
    }

    public function test_reauthorize_preserves_existing_refresh_and_client_credentials(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create([
            'credentials' => [
                'access_token' => 'old-access',
                'refresh_token' => 'keep-refresh',
                'client_id' => 'keep-client',
                'client_secret' => 'keep-secret',
            ],
        ]);

        $updated = app(StorageAccountService::class)->reauthorize($account, [
            'access_token' => 'new-access',
            'expires_in' => 1800,
        ]);

        $credentials = $updated->credentials ?? [];
        $this->assertSame('new-access', $credentials['access_token']);
        $this->assertSame('keep-refresh', $credentials['refresh_token']);
        $this->assertSame('keep-client', $credentials['client_id']);
        $this->assertSame('keep-secret', $credentials['client_secret']);
    }

    public function test_disconnect_promotes_next_default_and_dispatches_event(): void
    {
        Event::fake([StorageAccountDisconnected::class]);

        $default = StorageAccount::factory()->googlePhotos()->default()->create();
        $next = StorageAccount::factory()->googlePhotos()->create();

        app(StorageAccountService::class)->disconnect($default);

        $this->assertDatabaseMissing('storage_accounts', ['id' => $default->id]);
        $this->assertTrue($next->refresh()->is_default);

        Event::assertDispatched(StorageAccountDisconnected::class, function (StorageAccountDisconnected $event) use ($default): bool {
            return $event->accountId === $default->id;
        });
    }

    public function test_set_default_moves_default_flag_within_provider(): void
    {
        $currentDefault = StorageAccount::factory()->oneDrive()->default()->create();
        $candidate = StorageAccount::factory()->oneDrive()->create();

        app(StorageAccountService::class)->setDefault($candidate);

        $this->assertFalse($currentDefault->refresh()->is_default);
        $this->assertTrue($candidate->refresh()->is_default);
    }

    public function test_build_credentials_filters_empty_granted_scopes(): void
    {
        $credentials = app(StorageAccountService::class)->buildCredentials([
            'access_token' => 'token',
            'granted_scopes' => ['scope-a', '', 123, 'scope-b'],
        ]);

        $this->assertSame(['scope-a', 'scope-b'], $credentials['granted_scopes']);
    }

    public function test_connect_api_key_creates_r2_account(): void
    {
        Event::fake([StorageAccountConnected::class]);

        $account = app(StorageAccountService::class)->connectApiKey(
            StorageDriver::R2->value,
            'Archive bucket',
            StorageAccount::factory()->r2()->make()->credentials ?? [],
        );

        $this->assertSame(StorageDriver::R2->value, $account->provider);
        $this->assertTrue($account->is_default);
        Event::assertDispatched(StorageAccountConnected::class);
    }
}

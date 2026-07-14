<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageSettingsService;
use Modules\Storage\Tests\TestCase;

final class StorageSettingsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (StorageDriver::credentialProviders() as $driver) {
            $path = "storage_app.{$driver->value}";
            if (RuntimeConfig::has($path)) {
                RuntimeConfig::forget($path);
            }
        }
        RuntimeConfig::refresh();

        parent::tearDown();
    }

    public function test_accounts_apps_redirects_and_drivers_compose_settings_payload(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create(['is_default' => true]);
        RuntimeConfig::set('storage_app.google_photos', [
            'client_id' => 'photos-client',
            'client_secret' => 'photos-secret',
        ], 'json');
        RuntimeConfig::refresh();

        $settings = app(StorageSettingsService::class);

        $accounts = $settings->accounts();
        $this->assertCount(1, $accounts);
        $this->assertSame($account->id, $accounts->first()['id']);

        $apps = $settings->apps();
        $this->assertTrue($apps->contains(fn (array $row): bool => $row['provider'] === 'google_photos'));

        $redirects = $settings->redirects();
        $this->assertArrayHasKey('google_photos', $redirects);

        $drivers = $settings->drivers();
        $this->assertTrue($drivers->contains(fn (array $row): bool => $row['value'] === StorageDriver::R2->value));
        $this->assertTrue($drivers->contains(fn (array $row): bool => $row['value'] === StorageDriver::GooglePhotos->value));
    }
}

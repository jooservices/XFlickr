<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Illuminate\Validation\ValidationException;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Services\StorageAppProfileService;
use Modules\Storage\Tests\TestCase;

final class StorageAppProfileServiceTest extends TestCase
{
    private StorageAppProfileService $profiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profiles = app(StorageAppProfileService::class);
    }

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

    public function test_list_public_skips_missing_and_empty_configs(): void
    {
        RuntimeConfig::set('storage_app.google', 'not-an-array', 'string');
        RuntimeConfig::set('storage_app.google_photos', [
            'client_id' => '',
            'client_secret' => 'secret',
        ], 'json');
        RuntimeConfig::set('storage_app.onedrive', [
            'client_id' => 'onedrive-client-id',
            'client_secret' => 'onedrive-secret',
            'label' => 'OneDrive App',
        ], 'json');
        RuntimeConfig::refresh();

        $profiles = $this->profiles->listPublic();

        $this->assertFalse($profiles->contains(fn (array $row): bool => $row['provider'] === 'google'));
        $this->assertFalse($profiles->contains(fn (array $row): bool => $row['provider'] === 'google_photos'));
        $this->assertTrue($profiles->contains(fn (array $row): bool => $row['provider'] === 'onedrive'));
    }

    public function test_save_rejects_blank_credentials(): void
    {
        $this->expectException(ValidationException::class);

        $this->profiles->save([
            'provider' => StorageDriver::GoogleDrive->value,
            'client_id' => '',
            'client_secret' => '',
        ]);
    }

    public function test_save_persists_profile_with_default_redirect(): void
    {
        $provider = $this->profiles->save([
            'provider' => StorageDriver::GooglePhotos->value,
            'client_id' => 'photos-client',
            'client_secret' => 'photos-secret',
        ]);

        $this->assertSame(StorageDriver::GooglePhotos->value, $provider);
        $stored = RuntimeConfig::get('storage_app.google_photos');
        $this->assertIsArray($stored);
        $this->assertSame('photos-client', $stored['client_id']);
        $this->assertStringContainsString('/storage/callback/google_photos', $stored['redirect']);
        $this->assertTrue($this->profiles->hasProfiles());
    }

    public function test_default_redirects_cover_credential_providers(): void
    {
        $redirects = $this->profiles->defaultRedirects();

        foreach (StorageDriver::credentialProviders() as $driver) {
            $this->assertArrayHasKey($driver->value, $redirects);
            $this->assertStringContainsString("/storage/callback/{$driver->value}", $redirects[$driver->value]);
        }
    }
}

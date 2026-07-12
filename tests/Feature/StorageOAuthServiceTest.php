<?php

declare(strict_types=1);

namespace Tests\Feature;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Services\StorageOAuthService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageOAuthServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_google_oauth_provider_is_built_without_mutating_global_config(): void
    {
        $this->post('/settings/storage-app', [
            'provider' => StorageDriver::GooglePhotos->value,
            'client_id' => 'runtime-google-client',
            'client_secret' => 'runtime-google-secret',
            'redirect' => 'http://localhost/storage/callback/google_photos',
        ])->assertRedirect();

        config([
            'services.google.client_id' => 'original-google-client',
            'services.google.client_secret' => 'original-google-secret',
            'services.google.redirect' => 'http://localhost/original-google-callback',
        ]);

        $url = app(StorageOAuthService::class)->begin(StorageDriver::GooglePhotos);

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth?', $url);
        $this->assertStringContainsString('client_id=runtime-google-client', $url);
        $this->assertSame('original-google-client', config('services.google.client_id'));
        $this->assertSame('original-google-secret', config('services.google.client_secret'));
        $this->assertSame('http://localhost/original-google-callback', config('services.google.redirect'));
    }

    public function test_onedrive_oauth_provider_is_built_from_runtime_config(): void
    {
        $this->post('/settings/storage-app', [
            'provider' => StorageDriver::OneDrive->value,
            'client_id' => 'onedrive-client',
            'client_secret' => 'onedrive-secret',
            'redirect' => 'http://localhost/storage/callback/onedrive',
        ])->assertRedirect();

        $url = app(StorageOAuthService::class)->begin(StorageDriver::OneDrive);

        $this->assertStringStartsWith('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?', $url);
        $this->assertStringContainsString('client_id=onedrive-client', $url);
        $this->assertStringContainsString('Files.ReadWrite', urldecode($url));
        $this->assertSame(StorageDriver::OneDrive->value, session('storage_oauth_provider'));
        $this->assertTrue(RuntimeConfig::has('storage_app.onedrive'));
    }

    public function test_consume_return_url_rejects_external_hosts(): void
    {
        session(['storage_oauth_return_url' => 'https://evil.example/phish']);

        $url = app(StorageOAuthService::class)->consumeReturnUrl();

        $this->assertSame(route('settings.index', ['tab' => 'storage']), $url);
        $this->assertNull(session('storage_oauth_return_url'));
    }

    public function test_consume_return_url_allows_relative_paths(): void
    {
        session(['storage_oauth_return_url' => '/storages/google-photos?account_id=1']);

        $url = app(StorageOAuthService::class)->consumeReturnUrl();

        $this->assertSame('/storages/google-photos?account_id=1', $url);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
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

        $this->assertSame(route('connections.index', ['provider' => 'storage']), $url);
        $this->assertNull(session('storage_oauth_return_url'));
    }

    public function test_consume_return_url_allows_relative_paths(): void
    {
        session(['storage_oauth_return_url' => '/storages/google-photos?account_id=1']);

        $url = app(StorageOAuthService::class)->consumeReturnUrl();

        $this->assertSame('/storages/google-photos?account_id=1', $url);
    }

    public function test_consume_return_url_falls_back_when_missing(): void
    {
        $url = app(StorageOAuthService::class)->consumeReturnUrl();

        $this->assertSame(route('connections.index', ['provider' => 'storage']), $url);
    }

    public function test_begin_for_account_stores_account_id_in_session(): void
    {
        $this->post('/settings/storage-app', [
            'provider' => StorageDriver::GooglePhotos->value,
            'client_id' => 'runtime-google-client',
            'client_secret' => 'runtime-google-secret',
            'redirect' => 'http://localhost/storage/callback/google_photos',
        ])->assertRedirect();

        $account = StorageAccount::factory()->googlePhotos()->create();

        $url = app(StorageOAuthService::class)->beginForAccount($account, '/storages/google-photos');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth?', $url);
        $this->assertSame($account->id, session('storage_oauth_account_id'));
        $this->assertSame('/storages/google-photos', session('storage_oauth_return_url'));
    }

    public function test_begin_stores_optional_account_id_and_return_url(): void
    {
        $this->post('/settings/storage-app', [
            'provider' => StorageDriver::OneDrive->value,
            'client_id' => 'onedrive-client',
            'client_secret' => 'onedrive-secret',
            'redirect' => 'http://localhost/storage/callback/onedrive',
        ])->assertRedirect();

        app(StorageOAuthService::class)->begin(
            StorageDriver::OneDrive,
            accountId: 42,
            returnUrl: '/storages/onedrive',
        );

        $this->assertSame(42, session('storage_oauth_account_id'));
        $this->assertSame('/storages/onedrive', session('storage_oauth_return_url'));
    }
}

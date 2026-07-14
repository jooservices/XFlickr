<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Event;
use JOOservices\LaravelConfig\Facades\Config;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Events\StorageAccountDisconnected;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageR2ConnectionVerifier;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageAuthControllerTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_oauth_connect_redirects_to_provider_when_configured(): void
    {
        $this->post('/settings/storage-app', [
            'provider' => StorageDriver::OneDrive->value,
            'client_id' => 'onedrive-client',
            'client_secret' => 'onedrive-secret',
            'redirect' => 'http://localhost/storage/callback/onedrive',
        ])->assertRedirect();

        $response = $this->get('/storage/oauth/onedrive?return_url=/storages/onedrive');

        $response->assertRedirect();
        $this->assertStringStartsWith(
            'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_oauth_connect_fails_without_app_credentials(): void
    {
        Config::forget('storage_app.google_photos');
        Config::refresh();

        $response = $this->get('/storage/oauth/google_photos');

        $response->assertRedirect(route('connections.index', ['provider' => 'storage']));
        $response->assertSessionHas('error');
    }

    public function test_oauth_callback_rejects_denied_authorization(): void
    {
        session(['storage_oauth_return_url' => '/storages/google-photos']);

        $response = $this->get('/storage/callback/google_photos?error=access_denied');

        $response->assertRedirect('/storages/google-photos');
        $response->assertSessionHas('error', 'Storage authorization was denied.');
    }

    public function test_oauth_callback_rejects_invalid_state(): void
    {
        session([
            'storage_oauth_state' => 'expected-state',
            'storage_oauth_return_url' => '/storages/google-photos',
        ]);

        $response = $this->get('/storage/callback/google_photos?code=auth-code&state=wrong-state');

        $response->assertRedirect('/storages/google-photos');
        $response->assertSessionHas('error', 'Storage OAuth callback was incomplete.');
    }

    public function test_disconnect_removes_account(): void
    {
        Event::fake([StorageAccountDisconnected::class]);

        $account = StorageAccount::factory()->googlePhotos()->create();

        $response = $this->post('/storage/disconnect', ['account_id' => $account->id]);

        $response->assertRedirect(route('connections.index', ['provider' => 'storage']));
        $response->assertSessionHas('success', 'Storage account disconnected.');
        $this->assertDatabaseMissing('storage_accounts', ['id' => $account->id]);
        Event::assertDispatched(StorageAccountDisconnected::class);
    }

    public function test_disconnect_reports_missing_account(): void
    {
        $response = $this->post('/storage/disconnect', ['account_id' => 99999]);

        $response->assertRedirect(route('connections.index', ['provider' => 'storage']));
        $response->assertSessionHas('error', 'Storage account was not found.');
    }

    public function test_set_default_updates_default_account(): void
    {
        $current = StorageAccount::factory()->googlePhotos()->default()->create();
        $candidate = StorageAccount::factory()->googlePhotos()->create();

        $response = $this->post('/storage/set-default', ['account_id' => $candidate->id]);

        $response->assertRedirect(route('connections.index', ['provider' => 'storage']));
        $response->assertSessionHas('success', 'Default storage account updated.');
        $this->assertFalse($current->refresh()->is_default);
        $this->assertTrue($candidate->refresh()->is_default);
    }

    public function test_reauthorize_redirects_to_provider(): void
    {
        $this->post('/settings/storage-app', [
            'provider' => StorageDriver::GooglePhotos->value,
            'client_id' => 'google-client',
            'client_secret' => 'google-secret',
            'redirect' => 'http://localhost/storage/callback/google_photos',
        ])->assertRedirect();

        $account = StorageAccount::factory()->googlePhotos()->create();

        $response = $this->get('/storage/reauthorize/'.$account->id.'?return_url=/storages/google-photos');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth?', (string) $response->headers->get('Location'));
    }

    public function test_connect_r2_rejects_invalid_credentials(): void
    {
        $this->mock(StorageR2ConnectionVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')->once()->andThrow(new \RuntimeException('Invalid bucket'));
        });

        $response = $this->post('/storage/connect/r2', [
            'label' => 'Broken bucket',
            'access_key_id' => 'key',
            'secret_access_key' => 'secret',
            'bucket' => 'photos',
            'endpoint' => 'https://example.r2.cloudflarestorage.com',
        ]);

        $response->assertRedirect(route('connections.index', ['provider' => 'storage']));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('storage_accounts', 0);
    }

    public function test_set_default_reports_missing_account(): void
    {
        $response = $this->post('/storage/set-default', ['account_id' => 99999]);

        $response->assertRedirect(route('connections.index', ['provider' => 'storage']));
        $response->assertSessionHas('error', 'Storage account was not found.');
    }

    public function test_reauthorize_fails_without_app_credentials(): void
    {
        Config::forget('storage_app.google_photos');
        Config::refresh();

        $account = StorageAccount::factory()->googlePhotos()->create();

        $response = $this->get('/storage/reauthorize/'.$account->id);

        $response->assertRedirect(route('connections.index', ['provider' => 'storage']));
        $response->assertSessionHas('error');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Settings\Tests\Feature\Controllers;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageAppProfileControllerTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_can_save_google_drive_storage_credentials(): void
    {
        $response = $this->post('/settings/storage-app', [
            'provider' => 'google',
            'label' => 'Drive app',
            'client_id' => 'google-client-id',
            'client_secret' => 'google-client-secret',
            'redirect' => 'http://localhost:8082/storage/callback/google',
        ]);

        $response->assertRedirect(route('connections.index', ['provider' => 'storage']));

        $this->assertTrue(RuntimeConfig::has('storage_app.google'));
        $stored = RuntimeConfig::get('storage_app.google');
        $this->assertIsArray($stored);
        $this->assertSame('google-client-id', $stored['client_id']);
    }

    public function test_can_save_google_photos_storage_credentials(): void
    {
        $response = $this->post('/settings/storage-app', [
            'provider' => 'google_photos',
            'label' => 'Photos app',
            'client_id' => 'photos-client-id',
            'client_secret' => 'photos-client-secret',
        ]);

        $response->assertRedirect(route('connections.index', ['provider' => 'storage']));

        $this->assertTrue(RuntimeConfig::has('storage_app.google_photos'));
    }

    public function test_can_save_onedrive_storage_credentials(): void
    {
        $response = $this->post('/settings/storage-app', [
            'provider' => 'onedrive',
            'client_id' => 'onedrive-client-id',
            'client_secret' => 'onedrive-client-secret',
        ]);

        $response->assertRedirect(route('connections.index', ['provider' => 'storage']));

        $this->assertTrue(RuntimeConfig::has('storage_app.onedrive'));
    }
}

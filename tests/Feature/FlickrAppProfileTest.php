<?php

declare(strict_types=1);

namespace Tests\Feature;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Flickr\Services\FlickrAppProfileService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class FlickrAppProfileTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->bound('config-store')) {
            return;
        }

        foreach (array_keys(RuntimeConfig::group('xflickr_app')) as $profile) {
            RuntimeConfig::forget("xflickr_app.{$profile}");
        }
        RuntimeConfig::refresh();
    }

    protected function tearDown(): void
    {
        if (app()->bound('config-store')) {
            foreach (array_keys(RuntimeConfig::group('xflickr_app')) as $profile) {
                RuntimeConfig::forget("xflickr_app.{$profile}");
            }
            RuntimeConfig::refresh();
        }

        parent::tearDown();
    }

    public function test_can_save_flickr_app_credentials(): void
    {
        $response = $this->post('/settings/flickr-app', [
            'profile' => 'main',
            'label' => 'Test app',
            'api_key' => 'test-api-key-12345',
            'api_secret' => 'test-api-secret',
            'callback_url' => 'http://localhost:8082/flickr/callback',
        ]);

        $response->assertRedirect(route('settings.index', ['tab' => 'flickr']));

        $this->assertTrue(RuntimeConfig::has('xflickr_app.main'));
        $stored = RuntimeConfig::get('xflickr_app.main');
        $this->assertIsArray($stored);
        $this->assertSame('test-api-key-12345', $stored['apiKey']);
        $this->assertSame('test-api-secret', $stored['apiSecret']);
        $this->assertSame('http://localhost:8082/flickr/callback', $stored['callbackUrl']);
    }

    public function test_flickr_app_profile_requires_api_key(): void
    {
        $response = $this->post('/settings/flickr-app', [
            'api_secret' => 'test-api-secret',
        ]);

        $response->assertSessionHasErrors('api_key');
    }

    public function test_flickr_app_profile_rejects_invalid_callback_url(): void
    {
        $response = $this->post('/settings/flickr-app', [
            'profile' => 'invalid-callback',
            'api_key' => 'test-api-key-12345',
            'api_secret' => 'test-api-secret',
            'callback_url' => 'not-a-valid-url',
        ]);

        $response->assertSessionHasErrors('callback_url');
        $this->assertFalse(RuntimeConfig::has('xflickr_app.invalid-callback'));
    }

    public function test_saved_flickr_app_appears_in_settings_payload(): void
    {
        $this->post('/settings/flickr-app', [
            'profile' => 'main',
            'label' => 'Saved app',
            'api_key' => 'test-api-key-12345',
            'api_secret' => 'test-api-secret',
            'callback_url' => 'http://localhost:8082/flickr/callback',
        ])->assertRedirect();

        $response = $this->get('/settings?tab=flickr');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->has('flickr.apps', 1)
            ->where('flickr.apps.0.profile', 'main')
            ->where('flickr.apps.0.label', 'Saved app')
            ->where('flickr.apps.0.api_key_hint', 'test…2345'));
    }

    public function test_can_delete_flickr_app_profile_without_accounts(): void
    {
        $this->post('/settings/flickr-app', [
            'profile' => 'delete-me',
            'api_key' => 'test-api-key-12345',
            'api_secret' => 'test-api-secret',
            'callback_url' => 'http://localhost:8082/flickr/callback',
        ])->assertRedirect();

        $response = $this->delete('/settings/flickr-app/delete-me');

        $response->assertRedirect(route('settings.index', ['tab' => 'flickr']));
        $response->assertSessionHas('success');
        $this->assertFalse(RuntimeConfig::has('xflickr_app.delete-me'));
    }

    public function test_cannot_delete_flickr_app_profile_with_linked_accounts(): void
    {
        $this->post('/settings/flickr-app', [
            'profile' => 'linked',
            'api_key' => 'test-api-key-12345',
            'api_secret' => 'test-api-secret',
            'callback_url' => 'http://localhost:8082/flickr/callback',
        ])->assertRedirect();

        $this->createFlickrConnection(['app_profile' => 'linked']);

        $response = $this->delete('/settings/flickr-app/linked');

        $response->assertRedirect(route('settings.index', ['tab' => 'flickr']));
        $response->assertSessionHas('error');
        $this->assertTrue(RuntimeConfig::has('xflickr_app.linked'));
    }

    public function test_flickr_client_config_includes_callback_url(): void
    {
        $profiles = app(FlickrAppProfileService::class);
        $profiles->save([
            'profile' => 'main',
            'api_key' => 'key',
            'api_secret' => 'secret',
            'callback_url' => 'http://localhost:8082/flickr/callback',
        ]);

        $config = $profiles->flickrClientConfig('main');

        $this->assertSame('key', $config['apiKey']);
        $this->assertSame('secret', $config['apiSecret']);
        $this->assertSame('http://localhost:8082/flickr/callback', $config['callbackUrl']);
    }
}

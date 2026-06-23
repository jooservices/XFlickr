<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Flickr\FlickrAppProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Tests\TestCase;

final class FlickrAppProfileTest extends TestCase
{
    use RefreshDatabase;

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

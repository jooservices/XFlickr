<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services;

use Illuminate\Validation\ValidationException;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Flickr\Services\FlickrAppProfileService;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;

final class FlickrAppProfileServiceTest extends TestCase
{
    use CreatesFlickrConnection;

    private FlickrAppProfileService $profiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profiles = app(FlickrAppProfileService::class);
    }

    protected function tearDown(): void
    {
        foreach (['secondary', 'deletable'] as $profile) {
            if (RuntimeConfig::has("xflickr_app.{$profile}")) {
                RuntimeConfig::forget("xflickr_app.{$profile}");
            }
        }
        RuntimeConfig::refresh();

        parent::tearDown();
    }

    public function test_list_public_skips_invalid_profile_entries(): void
    {
        RuntimeConfig::set('xflickr_app.secondary', 'not-an-array', 'string');
        RuntimeConfig::set('xflickr_app.empty-key', ['apiKey' => '', 'apiSecret' => 'secret'], 'json');
        RuntimeConfig::refresh();

        $profiles = $this->profiles->listPublic();

        $this->assertFalse($profiles->contains(fn (array $row): bool => $row['profile'] === 'secondary'));
        $this->assertFalse($profiles->contains(fn (array $row): bool => $row['profile'] === 'empty-key'));
        $this->assertTrue($profiles->contains(fn (array $row): bool => $row['profile'] === 'main'));
    }

    public function test_save_rejects_missing_credentials(): void
    {
        $this->expectException(ValidationException::class);

        $this->profiles->save([
            'profile' => 'secondary',
            'api_key' => '',
            'api_secret' => '',
        ]);
    }

    public function test_delete_rejects_unknown_profile(): void
    {
        $this->expectException(ValidationException::class);

        $this->profiles->delete('missing-profile');
    }

    public function test_delete_rejects_profile_still_in_use(): void
    {
        $this->profiles->save([
            'profile' => 'deletable',
            'api_key' => 'delete-key',
            'api_secret' => 'delete-secret',
        ]);
        $this->createFlickrConnection(['app_profile' => 'deletable']);

        $this->expectException(ValidationException::class);

        $this->profiles->delete('deletable');
    }

    public function test_has_profiles_and_client_config_use_stored_callback_url(): void
    {
        $this->assertTrue($this->profiles->hasProfiles());

        $config = $this->profiles->flickrClientConfig('main');

        $this->assertSame('test-api-key-12345', $config['apiKey']);
        $this->assertStringContainsString('/flickr/callback', $config['callbackUrl']);
    }

    public function test_default_callback_url_uses_route(): void
    {
        $this->assertStringContainsString('/flickr/callback', $this->profiles->defaultCallbackUrl());
    }
}

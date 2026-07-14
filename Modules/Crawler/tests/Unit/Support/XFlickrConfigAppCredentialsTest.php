<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Support;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Exceptions\FlickrAppNotConfiguredException;
use Modules\Crawler\Support\XFlickrConfig;
use Modules\Crawler\Tests\TestCase;

final class XFlickrConfigAppCredentialsTest extends TestCase
{
    public function test_resolves_credentials_from_app_profile(): void
    {
        RuntimeConfig::set('xflickr_app.main', [
            'apiKey' => 'profile-key',
            'apiSecret' => 'profile-secret',
            'label' => 'Production',
        ], 'json');
        RuntimeConfig::refresh();

        $credentials = XFlickrConfig::appCredentials('main');

        $this->assertSame('profile-key', $credentials->apiKey);
        $this->assertSame('profile-secret', $credentials->apiSecret);
        $this->assertSame('Production', $credentials->label);
    }

    public function test_throws_when_profile_is_missing(): void
    {
        $this->expectException(FlickrAppNotConfiguredException::class);
        $this->expectExceptionMessage('xflickr_app.missing');

        XFlickrConfig::appCredentials('missing');
    }

    public function test_throws_when_credentials_are_incomplete(): void
    {
        RuntimeConfig::set('xflickr_app.incomplete', ['apiKey' => 'only-key'], 'json');
        RuntimeConfig::refresh();

        $this->expectException(FlickrAppNotConfiguredException::class);

        XFlickrConfig::appCredentials('incomplete');
    }

    public function test_sanitize_profile_slug_rejects_invalid_values(): void
    {
        $this->expectException(FlickrAppNotConfiguredException::class);

        XFlickrConfig::sanitizeProfileSlug('bad profile!');
    }

    public function test_default_app_profile_reads_static_config(): void
    {
        config(['xflickr-crawler.default_app_profile' => 'agency']);

        $this->assertSame('agency', XFlickrConfig::defaultAppProfile());
    }

    public function test_default_app_profile_prefers_runtime_override(): void
    {
        RuntimeConfig::set('xflickr.default_app_profile', 'runtime-main');
        RuntimeConfig::refresh();

        $this->assertSame('runtime-main', XFlickrConfig::defaultAppProfile());
    }
}

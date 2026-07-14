<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Client;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Exceptions\FlickrAppNotConfiguredException;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Tests\TestCase;

final class FlickrClientFactoryTest extends TestCase
{
    public function test_for_connection_resolves_app_profile_from_connection_row(): void
    {
        RuntimeConfig::set('xflickr_app.main', [
            'apiKey' => 'main-key',
            'apiSecret' => 'main-secret',
        ], 'json');
        RuntimeConfig::refresh();

        Connection::query()->create([
            'connection_key' => 'acct-main',
            'app_profile' => 'main',
            'token_payload' => $this->sampleToken(),
        ]);

        $client = app(FlickrClientFactory::class)->forConnection('acct-main');

        $this->assertNotNull($client);
    }

    public function test_for_connection_throws_when_app_profile_not_configured(): void
    {
        Connection::query()->create([
            'connection_key' => 'acct-missing',
            'app_profile' => 'unknown',
            'token_payload' => $this->sampleToken(),
        ]);

        $this->expectException(FlickrAppNotConfiguredException::class);

        app(FlickrClientFactory::class)->forConnection('acct-missing');
    }
}

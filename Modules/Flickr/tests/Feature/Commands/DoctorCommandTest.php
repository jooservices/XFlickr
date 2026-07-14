<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Feature\Commands;

use Database\Factories\Crawler\ConnectionFactory;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\FlickrCrawlerManager;
use Modules\Crawler\Models\Connection;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\IgnoresAuthentication;

final class DoctorCommandTest extends TestCase
{
    use IgnoresAuthentication;

    public function test_command_passes_when_all_checks_succeed(): void
    {
        $this->requiresRedis();

        $this->artisan('xflickr:flickr:doctor')
            ->expectsOutputToContain('All checks passed.')
            ->assertSuccessful();
    }

    public function test_command_reports_no_connected_flickr_accounts(): void
    {
        $this->requiresRedis();

        $this->artisan('xflickr:flickr:doctor')
            ->expectsOutputToContain('Flickr connections (none connected)')
            ->assertSuccessful();
    }

    public function test_command_passes_for_valid_connected_token(): void
    {
        $this->requiresRedis();

        $connection = $this->createConnectedAccount();

        $this->bindFakeFlickrTransport(
            FakeFlickrTransport::new()->pushJson([
                'stat' => 'ok',
                'user' => ['id' => $connection->connection_key],
            ]),
        );

        $this->artisan('xflickr:flickr:doctor')
            ->expectsOutputToContain("Flickr token [{$connection->username}]")
            ->expectsOutputToContain('All checks passed.')
            ->assertSuccessful();
    }

    public function test_command_fails_when_connected_token_probe_is_invalid(): void
    {
        $this->requiresRedis();

        $connection = $this->createConnectedAccount();

        $this->bindFakeFlickrTransport(
            FakeFlickrTransport::new()->pushJson([
                'stat' => 'fail',
                'code' => 99,
                'message' => 'Invalid auth token',
            ]),
        );

        $this->artisan('xflickr:flickr:doctor')
            ->expectsOutputToContain("Flickr token [{$connection->username}]")
            ->expectsOutputToContain('One or more checks failed.')
            ->assertFailed();
    }

    public function test_command_fails_when_connection_registry_cannot_be_loaded(): void
    {
        $this->requiresRedis();

        $manager = app(FlickrCrawlerManager::class);
        $partial = \Mockery::mock($manager)->makePartial();
        $partial->shouldReceive('connections')
            ->once()
            ->andThrow(new \RuntimeException('registry unavailable'));
        $this->app->instance(FlickrCrawlerManager::class, $partial);

        $this->artisan('xflickr:flickr:doctor')
            ->expectsOutputToContain('Flickr connections: registry unavailable')
            ->expectsOutputToContain('One or more checks failed.')
            ->assertFailed();
    }

    public function test_command_reports_unknown_credential_hint_when_app_profile_is_missing(): void
    {
        $this->requiresRedis();

        $connection = $this->createConnectedAccount([
            'app_profile' => 'missing-profile',
        ]);

        RuntimeConfig::forget('xflickr_app.missing-profile');
        RuntimeConfig::refresh();

        $this->bindFakeFlickrTransport(
            FakeFlickrTransport::new()->pushJson([
                'stat' => 'fail',
                'code' => 99,
                'message' => 'Invalid auth token',
            ]),
        );

        $this->artisan('xflickr:flickr:doctor')
            ->expectsOutputToContain('profile=missing-profile key=unknown')
            ->expectsOutputToContain('One or more checks failed.')
            ->assertFailed();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createConnectedAccount(array $attributes = []): Connection
    {
        /** @var Connection $connection */
        $connection = ConnectionFactory::new()->create($attributes);

        return $connection;
    }
}

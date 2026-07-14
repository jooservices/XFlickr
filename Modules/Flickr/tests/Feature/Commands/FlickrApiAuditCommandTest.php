<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Feature\Commands;

use JOOservices\Flickr\Client\FakeFlickrTransport;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\Support\IgnoresAuthentication;

final class FlickrApiAuditCommandTest extends TestCase
{
    use CreatesFlickrConnection;
    use IgnoresAuthentication;

    public function test_command_fails_when_no_connection_exists(): void
    {
        $this->artisan('xflickr:flickr:audit-api')
            ->expectsOutputToContain('No Flickr connection found.')
            ->assertFailed();
    }

    public function test_command_fails_when_app_credentials_are_missing(): void
    {
        RuntimeConfig::forget('xflickr_app.main');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection([
            'app_profile' => 'main',
        ]);

        $this->artisan('xflickr:flickr:audit-api', [
            'connection_key' => $connection->connection_key,
        ])
            ->expectsOutputToContain('App credentials missing:')
            ->assertFailed();
    }

    public function test_command_audits_active_connection(): void
    {
        $connection = $this->createFlickrConnection([
            'is_active' => true,
        ]);

        $this->bindFakeFlickrTransport(FakeFlickrTransport::new());

        $this->artisan('xflickr:flickr:audit-api')
            ->expectsOutputToContain("Connection: {$connection->connection_key}")
            ->assertSuccessful();
    }

    public function test_command_accepts_connection_key_and_contact_options(): void
    {
        $connection = $this->createFlickrConnection();

        $transport = FakeFlickrTransport::new()
            ->pushJson([
                'stat' => 'ok',
                'user' => ['id' => '24662369@N07'],
            ])
            ->pushJson([
                'stat' => 'ok',
                'person' => [
                    'username' => ['_content' => 'probe-user'],
                    'path_alias' => 'probe-alias',
                    'photos' => ['count' => ['_content' => 42]],
                ],
            ]);

        $this->bindFakeFlickrTransport($transport);

        $this->artisan('xflickr:flickr:audit-api', [
            'connection_key' => $connection->connection_key,
            '--contact' => '24662369@N07',
            '--url' => 'https://www.flickr.com/photos/probe-user/',
            '--photo-id' => '52957706290',
        ])
            ->expectsOutputToContain('Contact probes: 24662369@N07')
            ->expectsOutputToContain('Photo visibility probe: 52957706290')
            ->assertSuccessful();
    }

    public function test_command_warns_when_url_lookup_nsid_differs_from_contact(): void
    {
        $connection = $this->createFlickrConnection();

        $transport = FakeFlickrTransport::new();
        for ($i = 0; $i < 4; $i++) {
            $transport->pushJson(['stat' => 'ok']);
        }
        $transport->pushJson([
            'stat' => 'ok',
            'user' => ['id' => '99999999@N01'],
        ]);

        $this->bindFakeFlickrTransport($transport);

        $this->artisan('xflickr:flickr:audit-api', [
            'connection_key' => $connection->connection_key,
            '--contact' => '24662369@N07',
            '--url' => 'https://www.flickr.com/photos/other-user/',
        ])
            ->expectsOutputToContain('Contact NSID 24662369@N07 differs from URL lookup 99999999@N01')
            ->assertSuccessful();
    }

    public function test_command_reports_photo_probe_failure_when_get_info_fails(): void
    {
        $connection = $this->createFlickrConnection();

        $transport = FakeFlickrTransport::new();
        for ($i = 0; $i < 4; $i++) {
            $transport->pushJson(['stat' => 'ok']);
        }
        $transport->pushJson(['stat' => 'fail', 'code' => 1, 'message' => 'Photo not found']);
        $transport->pushJson(['stat' => 'fail', 'code' => 1, 'message' => 'Photo not found']);

        $this->bindFakeFlickrTransport($transport);

        $this->artisan('xflickr:flickr:audit-api', [
            'connection_key' => $connection->connection_key,
            '--photo-id' => 'missing-photo',
        ])
            ->expectsOutputToContain('photos.getInfo failed for photo missing-photo')
            ->assertSuccessful();
    }

    public function test_command_warns_when_find_by_username_nsid_differs(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();
        $otherNsid = FlickrNsid::fake();

        $transport = FakeFlickrTransport::new();
        for ($i = 0; $i < 4; $i++) {
            $transport->pushJson(['stat' => 'ok']);
        }
        $transport->pushJson([
            'stat' => 'ok',
            'person' => [
                'username' => ['_content' => 'probe-user'],
                'path_alias' => 'probe-alias',
                'photos' => ['count' => ['_content' => 1]],
            ],
        ]);
        $transport->pushJson([
            'stat' => 'ok',
            'user' => ['id' => $otherNsid],
        ]);
        for ($i = 0; $i < 6; $i++) {
            $transport->pushJson(['stat' => 'ok']);
        }

        $this->bindFakeFlickrTransport($transport);

        $this->artisan('xflickr:flickr:audit-api', [
            'connection_key' => $connection->connection_key,
            '--contact' => $contactNsid,
        ])
            ->expectsOutputToContain("findByUsername('probe-alias')")
            ->assertSuccessful();
    }

    public function test_command_resolves_contact_from_profile_url_when_contact_option_missing(): void
    {
        $connection = $this->createFlickrConnection();
        $resolvedNsid = FlickrNsid::fake();

        $transport = FakeFlickrTransport::new();
        for ($i = 0; $i < 4; $i++) {
            $transport->pushJson(['stat' => 'ok']);
        }
        $transport->pushJson([
            'stat' => 'ok',
            'user' => ['id' => $resolvedNsid],
        ]);
        for ($i = 0; $i < 10; $i++) {
            $transport->pushJson(['stat' => 'ok']);
        }

        $this->bindFakeFlickrTransport($transport);

        $this->artisan('xflickr:flickr:audit-api', [
            'connection_key' => $connection->connection_key,
            '--url' => 'https://www.flickr.com/photos/resolved-user/',
        ])
            ->expectsOutputToContain("Contact probes: {$resolvedNsid}")
            ->assertSuccessful();
    }

    public function test_command_reports_crawl_probe_failure_when_api_returns_error(): void
    {
        $connection = $this->createFlickrConnection();

        $transport = FakeFlickrTransport::new();
        $transport->pushJson(['stat' => 'ok']);
        $transport->pushJson(['stat' => 'fail', 'code' => 1, 'message' => 'contacts unavailable']);

        $this->bindFakeFlickrTransport($transport);

        $this->artisan('xflickr:flickr:audit-api', [
            'connection_key' => $connection->connection_key,
        ])
            ->expectsOutputToContain('flickr.contacts.getList')
            ->assertSuccessful();
    }

    public function test_command_reports_photo_visibility_success_when_signed_and_anonymous_probes_succeed(): void
    {
        $connection = $this->createFlickrConnection();

        $transport = FakeFlickrTransport::new();
        for ($i = 0; $i < 4; $i++) {
            $transport->pushJson(['stat' => 'ok']);
        }
        $transport->pushJson(['stat' => 'ok', 'photo' => ['id' => '52957706290']]);
        $transport->pushJson(['stat' => 'ok', 'photo' => ['id' => '52957706290']]);

        $this->bindFakeFlickrTransport($transport);

        $this->artisan('xflickr:flickr:audit-api', [
            'connection_key' => $connection->connection_key,
            '--photo-id' => '52957706290',
        ])
            ->expectsOutputToContain('App key can read photo 52957706290')
            ->assertSuccessful();
    }
}

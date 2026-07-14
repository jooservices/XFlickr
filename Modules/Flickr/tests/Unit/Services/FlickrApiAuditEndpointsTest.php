<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services;

use JOOservices\Flickr\Client\FakeFlickrTransport;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Tests\Support\ThrowingFlickrTransport;
use Modules\Flickr\Services\FlickrTokenHealthService;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;

final class FlickrApiAuditEndpointsTest extends TestCase
{
    use CreatesFlickrConnection;

    public function test_probe_crawl_reports_api_error_response(): void
    {
        $connection = $this->createFlickrConnection();
        $transport = FakeFlickrTransport::new()
            ->pushJson(['stat' => 'fail', 'code' => 2, 'message' => 'people crawl failed']);
        $factory = new FlickrClientFactory($transport);
        $client = $factory->forConnection($connection->connection_key);

        $result = (new FlickrTokenHealthService($factory))->probeCrawl(
            $client,
            'flickr.people.getPhotos',
            ['user_id' => $connection->connection_key, 'page' => 1, 'per_page' => 5],
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('people crawl failed', $result['message']);
        $this->assertSame(2, $result['code']);
    }

    public function test_probe_crawl_reports_transport_exception(): void
    {
        $connection = $this->createFlickrConnection();
        $factory = new FlickrClientFactory(new ThrowingFlickrTransport('crawl transport failure'));
        $client = $factory->forConnection($connection->connection_key);

        $result = (new FlickrTokenHealthService($factory))->probeCrawl(
            $client,
            'flickr.people.getPhotos',
            ['user_id' => $connection->connection_key, 'page' => 1, 'per_page' => 5],
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('crawl transport failure', $result['message']);
    }

    public function test_probe_with_response_catch_returns_error_payload(): void
    {
        $connection = $this->createFlickrConnection();
        $factory = new FlickrClientFactory(new ThrowingFlickrTransport('signed transport failure'));
        $client = $factory->forConnection($connection->connection_key);

        $result = (new FlickrTokenHealthService($factory))->probeWithResponse($client, 'flickr.test.login', []);

        $this->assertFalse($result['ok']);
        $this->assertSame('signed transport failure', $result['message']);
    }

    public function test_audit_endpoints_classifies_invalid_token_and_api_errors(): void
    {
        $connection = $this->createFlickrConnection();

        $this->bindFakeFlickrTransport(
            FakeFlickrTransport::new()
                ->pushJson(['stat' => 'fail', 'code' => 98, 'message' => 'Invalid auth token'])
                ->pushJson(['stat' => 'fail', 'code' => 1, 'message' => 'contacts unavailable'])
                ->pushJson(['stat' => 'ok', 'photos' => ['total' => 0]])
                ->pushJson(['stat' => 'ok', 'photos' => ['total' => 0]]),
        );

        $report = app(FlickrTokenHealthService::class)->auditEndpoints($connection);

        $this->assertSame($connection->connection_key, $report->connectionKey);
        $this->assertNotSame([], $report->entries);

        $login = $report->entries[0];
        $this->assertSame('probe', $login['type']);
        $this->assertSame('flickr.test.login', $login['method']);
        $this->assertFalse($login['ok']);
        $this->assertSame(98, $login['code']);
    }
}

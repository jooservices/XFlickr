<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Commands;

use Illuminate\Console\OutputStyle;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Tests\Support\ThrowingFlickrTransport;
use Modules\Flickr\Console\Commands\FlickrApiAuditCommand;
use Modules\Flickr\Tests\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\CreatesFlickrConnection;

final class FlickrApiAuditCommandInternalsTest extends TestCase
{
    use CreatesFlickrConnection;

    public function test_probe_crawl_reports_api_error_response(): void
    {
        $connection = $this->createFlickrConnection();
        $transport = FakeFlickrTransport::new()
            ->pushJson(['stat' => 'fail', 'code' => 2, 'message' => 'people crawl failed']);
        $client = (new FlickrClientFactory($transport))->forConnection($connection->connection_key);

        $command = app(FlickrApiAuditCommand::class);
        $output = new BufferedOutput;
        $command->setOutput(new OutputStyle(
            new ArrayInput([]),
            $output,
        ));

        $method = new ReflectionMethod(FlickrApiAuditCommand::class, 'probeCrawl');
        $method->setAccessible(true);
        $method->invoke(
            $command,
            $client,
            'flickr.people.getPhotos',
            ['user_id' => $connection->connection_key, 'page' => 1, 'per_page' => 5],
        );

        $this->assertStringContainsString('people crawl failed', $output->fetch());
    }

    public function test_probe_crawl_reports_transport_exception(): void
    {
        $connection = $this->createFlickrConnection();
        $client = (new FlickrClientFactory(new ThrowingFlickrTransport('crawl transport failure')))
            ->forConnection($connection->connection_key);

        $command = app(FlickrApiAuditCommand::class);
        $output = new BufferedOutput;
        $command->setOutput(new OutputStyle(
            new ArrayInput([]),
            $output,
        ));

        $method = new ReflectionMethod(FlickrApiAuditCommand::class, 'probeCrawl');
        $method->setAccessible(true);
        $method->invoke(
            $command,
            $client,
            'flickr.people.getPhotos',
            ['user_id' => $connection->connection_key, 'page' => 1, 'per_page' => 5],
        );

        $this->assertStringContainsString('crawl transport failure', $output->fetch());
    }

    public function test_probe_with_response_catch_returns_error_payload(): void
    {
        $connection = $this->createFlickrConnection();
        $client = (new FlickrClientFactory(new ThrowingFlickrTransport('signed transport failure')))
            ->forConnection($connection->connection_key);

        $command = app(FlickrApiAuditCommand::class);
        $method = new ReflectionMethod(FlickrApiAuditCommand::class, 'probeWithResponse');
        $method->setAccessible(true);

        $result = $method->invoke($command, $client, 'flickr.test.login', []);

        $this->assertFalse($result['ok']);
        $this->assertSame('signed transport failure', $result['message']);
    }
}

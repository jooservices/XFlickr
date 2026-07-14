<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Services;

use Modules\Crawler\FlickrConnection;
use Modules\Crawler\Services\CrawlingService;
use Modules\Crawler\Tests\TestCase;

final class FlickrConnectionTest extends TestCase
{
    public function test_connection_key_accessor(): void
    {
        $connection = new FlickrConnection(
            'key-1',
            $this->sampleToken(),
            'main',
            app(CrawlingService::class),
        );

        $this->assertSame('key-1', $connection->connectionKey());
    }
}

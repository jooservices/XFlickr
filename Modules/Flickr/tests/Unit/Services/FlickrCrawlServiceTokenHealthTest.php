<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Enums\CrawlType;
use Modules\Flickr\Exceptions\FlickrTokenInvalidException;
use Modules\Flickr\Exceptions\GlobalCrawlPauseException;
use Modules\Flickr\Services\FlickrCrawlService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class FlickrCrawlServiceTokenHealthTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    protected function tearDown(): void
    {
        if (app()->bound('config-store') && RuntimeConfig::has('xflickr.global_pause')) {
            RuntimeConfig::forget('xflickr.global_pause');
            RuntimeConfig::refresh();
        }

        parent::tearDown();
    }

    public function test_crawl_throws_when_token_is_invalid(): void
    {
        $connection = $this->createFlickrConnection();

        $this->mockFlickrTokenHealth(valid: false);

        $this->expectException(FlickrTokenInvalidException::class);

        app(FlickrCrawlService::class)->crawl($connection, CrawlType::Photos, FlickrNsid::fake());
    }

    public function test_crawl_throws_when_global_pause_is_active(): void
    {
        $connection = $this->createFlickrConnection();

        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $this->expectException(GlobalCrawlPauseException::class);

        app(FlickrCrawlService::class)->crawl($connection, CrawlType::Photos, FlickrNsid::fake());
    }
}

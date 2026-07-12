<?php

declare(strict_types=1);

namespace Tests\Feature\Flickr;

use JOOservices\XFlickrCrawler\Enums\CrawlType;
use Modules\Flickr\Exceptions\FlickrTokenInvalidException;
use Modules\Flickr\Services\FlickrCrawlService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class FlickrCrawlServiceTokenHealthTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_crawl_throws_when_token_is_invalid(): void
    {
        $connection = $this->createFlickrConnection();

        $this->mockFlickrTokenHealth(valid: false);

        $this->expectException(FlickrTokenInvalidException::class);

        app(FlickrCrawlService::class)->crawl($connection, CrawlType::Photos, '555@N01');
    }
}

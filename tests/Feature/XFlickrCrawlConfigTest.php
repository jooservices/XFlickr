<?php

declare(strict_types=1);

namespace Tests\Feature;

use JOOservices\XFlickrCrawler\Support\XFlickrConfig;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class XFlickrCrawlConfigTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_crawl_config_falls_back_to_file_config_without_invalid_runtime_path(): void
    {
        $this->assertSame(15, XFlickrConfig::crawlInt('stall_minutes', 15));
        $this->assertSame(0, XFlickrConfig::crawlInt('dispatch_limit', 1));
    }
}

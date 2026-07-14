<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Support;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Support\FlickrCrawlQueryParams;
use Modules\Crawler\Tests\TestCase;

final class FlickrCrawlQueryParamsTest extends TestCase
{
    public function test_visibility_filters_omitted_by_default(): void
    {
        RuntimeConfig::forget('xflickr_crawl.safe_search');
        RuntimeConfig::forget('xflickr_crawl.privacy_filter');
        RuntimeConfig::forget('xflickr_crawl.people_photos_safe_search');
        RuntimeConfig::refresh();

        $query = FlickrCrawlQueryParams::peoplePhotos('59998846@N06', 1, 100);

        $this->assertArrayNotHasKey('safe_search', $query);
        $this->assertArrayNotHasKey('privacy_filter', $query);
    }

    public function test_legacy_people_photos_safe_search_key_is_honored(): void
    {
        RuntimeConfig::forget('xflickr_crawl.safe_search');
        RuntimeConfig::set('xflickr_crawl.people_photos_safe_search', 2, 'int');
        RuntimeConfig::refresh();

        $filters = FlickrCrawlQueryParams::visibilityFilters();

        $this->assertSame(2, $filters['safe_search']);
    }

    public function test_explicit_filters_are_included_when_configured(): void
    {
        RuntimeConfig::set('xflickr_crawl.safe_search', 3, 'int');
        RuntimeConfig::set('xflickr_crawl.privacy_filter', 4, 'int');
        RuntimeConfig::refresh();

        $filters = FlickrCrawlQueryParams::visibilityFilters();

        $this->assertSame(3, $filters['safe_search']);
        $this->assertSame(4, $filters['privacy_filter']);
    }
}

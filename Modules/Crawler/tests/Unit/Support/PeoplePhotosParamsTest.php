<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Support;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Support\PeoplePhotosParams;
use Modules\Crawler\Tests\TestCase;

final class PeoplePhotosParamsTest extends TestCase
{
    public function test_query_omits_safe_search_by_default(): void
    {
        RuntimeConfig::forget('xflickr_crawl.people_photos_safe_search');
        RuntimeConfig::refresh();

        $query = PeoplePhotosParams::query('59998846@N06', 1, 500);

        $this->assertSame('59998846@N06', $query['user_id']);
        $this->assertArrayNotHasKey('safe_search', $query);
    }

    public function test_query_includes_safe_search_when_configured(): void
    {
        RuntimeConfig::set('xflickr_crawl.people_photos_safe_search', 2, 'int');
        RuntimeConfig::refresh();

        $query = PeoplePhotosParams::query('59998846@N06', 1, 500);

        $this->assertSame(2, $query['safe_search']);
    }
}

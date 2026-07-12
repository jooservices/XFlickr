<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Unit\Services;

use Modules\Operations\Services\CrawlOperationsService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class CrawlOperationsServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    #[Test]
    public function page_props_include_accounts_and_spider_flag(): void
    {
        $this->createFlickrConnection([
            'connection_key' => 'a@N01',
            'username' => 'alpha',
        ]);

        $props = app(CrawlOperationsService::class)->pageProps();

        $this->assertArrayHasKey('spiderEnabled', $props);
        $this->assertIsBool($props['spiderEnabled']);
        $this->assertCount(1, $props['accounts']);
        $this->assertSame('a@N01', $props['accounts']->first()['nsid']);
    }
}

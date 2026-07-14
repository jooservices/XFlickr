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
    public function page_props_include_accounts(): void
    {
        $connection = $this->createFlickrConnection([
            'username' => 'alpha',
        ]);

        $props = app(CrawlOperationsService::class)->pageProps();

        $this->assertArrayNotHasKey('spiderEnabled', $props);
        $this->assertCount(1, $props['accounts']);
        $this->assertSame($connection->connection_key, $props['accounts']->first()['nsid']);
    }
}

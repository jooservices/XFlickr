<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Operations\Services\ServicesDependencyProbeService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class ServicesDependencyProbeServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    #[Test]
    public function probe_all_returns_mysql_redis_and_mongodb_shapes(): void
    {
        Cache::forget('xflickr:operations:dependency:mysql');
        Cache::forget('xflickr:operations:dependency:redis');
        Cache::forget('xflickr:operations:dependency:mongodb');

        $probes = app(ServicesDependencyProbeService::class)->probeAll();

        foreach (['mysql', 'redis', 'mongodb'] as $key) {
            $this->assertArrayHasKey($key, $probes);
            $this->assertArrayHasKey('ok', $probes[$key]);
            $this->assertArrayHasKey('latency_ms', $probes[$key]);
            $this->assertArrayHasKey('detail', $probes[$key]);
            $this->assertIsBool($probes[$key]['ok']);
        }

        $this->assertTrue($probes['mysql']['ok']);
    }

    #[Test]
    public function probe_all_is_cached_for_repeated_calls(): void
    {
        Cache::forget('xflickr:operations:dependency:mysql');
        Cache::forget('xflickr:operations:dependency:redis');
        Cache::forget('xflickr:operations:dependency:mongodb');

        $service = app(ServicesDependencyProbeService::class);
        $first = $service->probeAll();
        $second = $service->probeAll();

        $this->assertSame($first, $second);
    }
}

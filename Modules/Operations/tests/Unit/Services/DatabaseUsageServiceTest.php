<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Operations\Services\DatabaseUsageService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class DatabaseUsageServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    #[Test]
    public function snapshot_reports_reachable_sql_store_and_history_shape(): void
    {
        Cache::forget('xflickr:database:usage');
        Cache::forget('xflickr:database:usage:history');

        $snapshot = app(DatabaseUsageService::class)->snapshot();

        $this->assertSame('ok', $snapshot['mysql']['status']);
        $this->assertSame('sqlite', $snapshot['mysql']['driver']);
        $this->assertIsInt($snapshot['mysql']['size_bytes']);
        $this->assertGreaterThanOrEqual(0, $snapshot['mysql']['size_bytes']);
        $this->assertNull($snapshot['mysql']['connections_current']);
        $this->assertNull($snapshot['mysql']['connections_max']);
        $this->assertIsArray($snapshot['mysql']['tables']);

        $this->assertArrayHasKey('status', $snapshot['mongodb']);
        $this->assertContains($snapshot['mongodb']['status'], ['ok', 'error']);
        $this->assertSame('mongodb', $snapshot['mongodb']['driver']);

        $this->assertIsArray($snapshot['history']);
        $this->assertNotEmpty($snapshot['history']);
        $this->assertArrayHasKey('t', $snapshot['history'][0]);
        $this->assertArrayHasKey('mysql_size_bytes', $snapshot['history'][0]);
        $this->assertArrayHasKey('mongodb_size_bytes', $snapshot['history'][0]);
    }

    #[Test]
    public function snapshot_is_cached_for_repeated_calls(): void
    {
        Cache::forget('xflickr:database:usage');
        Cache::forget('xflickr:database:usage:history');

        $service = app(DatabaseUsageService::class);
        $first = $service->snapshot();
        $second = $service->snapshot();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function snapshot_history_skips_samples_within_interval(): void
    {
        Cache::forget('xflickr:database:usage');
        Cache::forget('xflickr:database:usage:history');

        $service = app(DatabaseUsageService::class);
        $first = $service->snapshot();
        Cache::forget('xflickr:database:usage');
        $second = $service->snapshot();

        $this->assertSame(count($first['history']), count($second['history']));
    }

    #[Test]
    public function snapshot_prunes_history_older_than_retention_window(): void
    {
        Cache::forget('xflickr:database:usage');
        Cache::put('xflickr:database:usage:history', [
            [
                't' => now()->subDays(2)->getTimestamp(),
                'mysql_size_bytes' => 10,
                'mysql_connections' => 1,
                'mongodb_size_bytes' => 20,
            ],
        ], now()->addHour());

        $snapshot = app(DatabaseUsageService::class)->snapshot();

        $this->assertCount(1, $snapshot['history']);
        $this->assertGreaterThan(now()->subDay()->getTimestamp(), $snapshot['history'][0]['t']);
    }

    #[Test]
    public function snapshot_treats_non_array_history_cache_as_empty(): void
    {
        Cache::forget('xflickr:database:usage');
        Cache::forever('xflickr:database:usage:history', 'corrupt');

        $snapshot = app(DatabaseUsageService::class)->snapshot();

        $this->assertIsArray($snapshot['history']);
        $this->assertCount(1, $snapshot['history']);
    }
}

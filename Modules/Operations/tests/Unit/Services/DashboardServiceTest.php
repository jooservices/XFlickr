<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Operations\Services\DashboardService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class DashboardServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    #[Test]
    public function snapshot_builds_global_and_per_account_shape(): void
    {
        $this->createFlickrConnection([
            'connection_key' => 'a@N01',
            'username' => 'alpha',
            'fullname' => 'Alpha',
        ]);

        Cache::forget('xflickr:dashboard:snapshot');

        $snapshot = app(DashboardService::class)->snapshot();

        $this->assertArrayHasKey('generated_at', $snapshot);
        $this->assertSame(1, $snapshot['global']['accounts']);
        $this->assertArrayHasKey('stored_files', $snapshot['global']);
        $this->assertArrayHasKey('any_cooldown', $snapshot['alerts']);
        $this->assertCount(1, $snapshot['accounts']);
        $this->assertSame('a@N01', $snapshot['accounts'][0]['account']['nsid']);
        $this->assertArrayHasKey('rate_limit', $snapshot['accounts'][0]);
        $this->assertArrayHasKey('transfers', $snapshot['accounts'][0]);
        $this->assertArrayHasKey('databases', $snapshot);
        $this->assertArrayHasKey('mysql', $snapshot['databases']);
        $this->assertArrayHasKey('mongodb', $snapshot['databases']);
        $this->assertArrayHasKey('history', $snapshot['databases']);
        $this->assertArrayHasKey('database_unreachable', $snapshot['alerts']);
        $this->assertArrayHasKey('mysql_connections_high', $snapshot['alerts']);
    }
}

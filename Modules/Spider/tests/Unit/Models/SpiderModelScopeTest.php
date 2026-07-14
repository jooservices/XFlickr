<?php

declare(strict_types=1);

namespace Modules\Spider\Tests\Unit\Models;

use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Models\SpiderFrontierItem;
use Modules\Spider\Models\SpiderRun;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class SpiderModelScopeTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_spider_run_for_connection_and_running_scopes(): void
    {
        $matchKey = FlickrNsid::fake();
        $otherKey = FlickrNsid::fake();

        $running = SpiderRun::factory()->create([
            'connection_key' => $matchKey,
            'status' => SpiderRunStatus::Running,
        ]);
        SpiderRun::factory()->create([
            'connection_key' => $matchKey,
            'status' => SpiderRunStatus::Completed,
        ]);
        SpiderRun::factory()->create([
            'connection_key' => $otherKey,
            'status' => SpiderRunStatus::Running,
        ]);

        $this->assertTrue(
            SpiderRun::query()->forConnection($matchKey)->running()->whereKey($running->id)->exists(),
        );
        $this->assertFalse(
            SpiderRun::query()->forConnection($otherKey)->running()->whereKey($running->id)->exists(),
        );
        $this->assertSame(1, SpiderRun::query()->forConnection($matchKey)->withStatus(SpiderRunStatus::Completed)->count());
    }

    public function test_spider_frontier_item_status_scopes(): void
    {
        $pending = SpiderFrontierItem::factory()->create([
            'status' => SpiderFrontierStatus::Pending,
        ]);
        SpiderFrontierItem::factory()->create([
            'status' => SpiderFrontierStatus::Crawled,
        ]);

        $this->assertTrue(SpiderFrontierItem::query()->pending()->whereKey($pending->id)->exists());
        $this->assertFalse(SpiderFrontierItem::query()->crawled()->whereKey($pending->id)->exists());
        $this->assertSame(1, SpiderFrontierItem::query()->withStatus(SpiderFrontierStatus::Crawled)->count());
    }
}

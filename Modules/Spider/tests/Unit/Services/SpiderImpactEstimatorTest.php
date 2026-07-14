<?php

declare(strict_types=1);

namespace Modules\Spider\Tests\Unit\Services;

use Modules\Spider\Services\SpiderImpactEstimator;
use Tests\TestCase;

final class SpiderImpactEstimatorTest extends TestCase
{
    public function test_estimate_without_saved_contacts_returns_ceiling_only(): void
    {
        $estimate = app(SpiderImpactEstimator::class)->estimate(2, 25, 500);

        $this->assertNull($estimate['contacts_known']);
        $this->assertNull($estimate['contacts_known_capped']);
        $this->assertNull($estimate['crawl_targets_known']);
        $this->assertSame(500, $estimate['contacts_ceiling']);
        $this->assertSame(1 + (500 * 2), $estimate['crawl_targets_ceiling']);
        $this->assertSame(50, $estimate['crawl_targets_per_tick']);
        $this->assertSame(2, $estimate['max_depth']);
    }

    public function test_estimate_with_saved_contacts_caps_known_workload(): void
    {
        $estimate = app(SpiderImpactEstimator::class)->estimate(2, 25, 500, 120);

        $this->assertSame(120, $estimate['contacts_known']);
        $this->assertSame(120, $estimate['contacts_known_capped']);
        $this->assertSame(1 + (120 * 2), $estimate['crawl_targets_known']);
        $this->assertSame(1 + (500 * 2), $estimate['crawl_targets_ceiling']);
    }

    public function test_estimate_caps_known_contacts_to_max_total(): void
    {
        $estimate = app(SpiderImpactEstimator::class)->estimate(1, 10, 50, 999);

        $this->assertSame(999, $estimate['contacts_known']);
        $this->assertSame(50, $estimate['contacts_known_capped']);
        $this->assertSame(1 + (50 * 2), $estimate['crawl_targets_known']);
        $this->assertSame(1 + (50 * 2), $estimate['crawl_targets_ceiling']);
        $this->assertSame(20, $estimate['crawl_targets_per_tick']);
    }
}

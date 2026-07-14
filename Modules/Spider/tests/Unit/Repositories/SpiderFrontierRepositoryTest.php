<?php

declare(strict_types=1);

namespace Modules\Spider\Tests\Unit\Repositories;

use Modules\Spider\Database\Factories\SpiderFrontierItemFactory;
use Modules\Spider\Database\Factories\SpiderRunFactory;
use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Repositories\SpiderFrontierRepository;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class SpiderFrontierRepositoryTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_enqueue_creates_pending_item_once(): void
    {
        $run = SpiderRunFactory::new()->create();
        $contactNsid = FlickrNsid::fake();
        $repository = app(SpiderFrontierRepository::class);

        $this->assertTrue($repository->enqueue($run->id, $contactNsid, 1));
        $this->assertFalse($repository->enqueue($run->id, $contactNsid, 1));

        $this->assertDatabaseCount('spider_frontier_items', 1);
    }

    public function test_next_pending_orders_by_depth_then_id(): void
    {
        $run = SpiderRunFactory::new()->create();
        $repository = app(SpiderFrontierRepository::class);

        $deeper = SpiderFrontierItemFactory::new()->create([
            'spider_run_id' => $run->id,
            'contact_nsid' => FlickrNsid::fake(),
            'depth' => 2,
            'status' => SpiderFrontierStatus::Pending,
        ]);
        $shallow = SpiderFrontierItemFactory::new()->create([
            'spider_run_id' => $run->id,
            'contact_nsid' => FlickrNsid::fake(),
            'depth' => 0,
            'status' => SpiderFrontierStatus::Pending,
        ]);

        $pending = $repository->nextPending($run->id, 1);

        $this->assertCount(1, $pending);
        $this->assertTrue($pending->first()?->is($shallow));
        $this->assertFalse($pending->first()?->is($deeper));
    }

    public function test_depth_histogram_and_status_counts(): void
    {
        $run = SpiderRunFactory::new()->create();
        $repository = app(SpiderFrontierRepository::class);

        SpiderFrontierItemFactory::new()->count(2)->create([
            'spider_run_id' => $run->id,
            'depth' => 0,
            'status' => SpiderFrontierStatus::Pending,
        ]);
        SpiderFrontierItemFactory::new()->create([
            'spider_run_id' => $run->id,
            'depth' => 1,
            'status' => SpiderFrontierStatus::Crawled,
        ]);

        $this->assertSame([0 => 2, 1 => 1], $repository->depthHistogram($run->id));
        $this->assertSame(2, $repository->countByStatus($run->id, SpiderFrontierStatus::Pending));
        $this->assertSame(1, $repository->countByStatus($run->id, SpiderFrontierStatus::Crawled));
    }

    public function test_known_contact_nsids_returns_all_frontier_nsids(): void
    {
        $run = SpiderRunFactory::new()->create();
        $first = FlickrNsid::fake();
        $second = FlickrNsid::fake();

        SpiderFrontierItemFactory::new()->create([
            'spider_run_id' => $run->id,
            'contact_nsid' => $first,
        ]);
        SpiderFrontierItemFactory::new()->create([
            'spider_run_id' => $run->id,
            'contact_nsid' => $second,
        ]);

        $known = app(SpiderFrontierRepository::class)->knownContactNsids($run->id);

        $this->assertEqualsCanonicalizing([$first, $second], $known);
    }
}

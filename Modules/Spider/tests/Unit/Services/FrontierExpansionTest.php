<?php

declare(strict_types=1);

namespace Modules\Spider\Tests\Unit\Services;

use Modules\Spider\Database\Factories\SpiderFrontierItemFactory;
use Modules\Spider\Database\Factories\SpiderRunFactory;
use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Repositories\SpiderFrontierRepository;
use Modules\Spider\Services\FrontierExpansion;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class FrontierExpansionTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_enqueue_discovered_skips_known_and_beyond_max_depth(): void
    {
        $run = SpiderRunFactory::new()->create([
            'max_depth' => 1,
            'contacts_discovered' => 0,
        ]);
        $frontier = app(SpiderFrontierRepository::class);
        $knownNsid = FlickrNsid::fake();
        $newNsid = FlickrNsid::fake();
        $tooDeepNsid = FlickrNsid::fake();

        SpiderFrontierItemFactory::new()->create([
            'spider_run_id' => $run->id,
            'contact_nsid' => $knownNsid,
            'depth' => 0,
            'status' => SpiderFrontierStatus::Pending,
        ]);

        app(FrontierExpansion::class)->enqueueDiscovered(
            $run,
            $frontier,
            [$knownNsid, $newNsid, $tooDeepNsid],
            depth: 2,
        );

        $run->refresh();
        $this->assertSame(0, $run->contacts_discovered);
        $this->assertDatabaseMissing('spider_frontier_items', [
            'spider_run_id' => $run->id,
            'contact_nsid' => $tooDeepNsid,
        ]);

        app(FrontierExpansion::class)->enqueueDiscovered(
            $run,
            $frontier,
            [$newNsid],
            depth: 1,
        );

        $run->refresh();
        $this->assertSame(1, $run->contacts_discovered);
        $this->assertDatabaseHas('spider_frontier_items', [
            'spider_run_id' => $run->id,
            'contact_nsid' => $newNsid,
            'depth' => 1,
        ]);
    }

    public function test_maybe_complete_run_marks_completed_when_frontier_is_drained(): void
    {
        $run = SpiderRunFactory::new()->create([
            'status' => SpiderRunStatus::Running,
        ]);
        SpiderFrontierItemFactory::new()->create([
            'spider_run_id' => $run->id,
            'contact_nsid' => FlickrNsid::fake(),
            'depth' => 0,
            'status' => SpiderFrontierStatus::Crawled,
        ]);

        app(FrontierExpansion::class)->maybeCompleteRun($run, app(SpiderFrontierRepository::class));

        $run->refresh();
        $this->assertSame(SpiderRunStatus::Completed, $run->status);
        $this->assertNotNull($run->completed_at);
    }

    public function test_pause_missing_connection_pauses_run(): void
    {
        $run = SpiderRunFactory::new()->create([
            'status' => SpiderRunStatus::Running,
        ]);

        app(FrontierExpansion::class)->pauseMissingConnection($run);

        $run->refresh();
        $this->assertSame(SpiderRunStatus::Paused, $run->status);
        $this->assertNotNull($run->paused_at);
    }
}

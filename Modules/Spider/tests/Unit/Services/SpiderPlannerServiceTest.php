<?php

declare(strict_types=1);

namespace Modules\Spider\Tests\Unit\Services;

use Illuminate\Support\Facades\Queue;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Contacts\Database\Factories\ContactFullPassRunFactory;
use Modules\Crawler\Events\ContactsCrawlCompleted;
use Modules\Crawler\Models\SubjectContact;
use Modules\Flickr\Exceptions\GlobalCrawlPauseException;
use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Models\SpiderFrontierItem;
use Modules\Spider\Models\SpiderRun;
use Modules\Spider\Services\SpiderPlannerService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class SpiderPlannerServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->bound('config-store')) {
            $this->markTestSkipped('Runtime config store is not available.');
        }
    }

    public function test_status_reports_inactive_when_no_run_exists(): void
    {
        $connection = $this->createFlickrConnection();

        $status = app(SpiderPlannerService::class)->statusForConnection($connection->connection_key);

        $this->assertFalse($status['active']);
        $this->assertNull($status['run']);
    }

    public function test_stop_pauses_active_run(): void
    {
        $connection = $this->createFlickrConnection();

        SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 2,
        ]);

        $run = app(SpiderPlannerService::class)->stop($connection);

        $this->assertNotNull($run);
        $this->assertSame(SpiderRunStatus::Paused, $run->status);
    }

    public function test_start_refuses_when_disabled(): void
    {
        RuntimeConfig::set('spider.enabled', false, 'bool');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection();

        $this->expectException(\RuntimeException::class);

        app(SpiderPlannerService::class)->start($connection);
    }

    public function test_contacts_crawl_completed_seeds_frontier_at_depth_zero(): void
    {
        $connection = $this->createFlickrConnection();

        $run = SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 2,
        ]);

        $crawlRunId = 1;
        foreach (['a@N01', 'b@N02'] as $nsid) {
            SubjectContact::query()->create([
                'connection_key' => $connection->connection_key,
                'subject_nsid' => $connection->nsid ?? 'root@N00',
                'contact_nsid' => $nsid,
                'crawl_run_id' => $crawlRunId,
                'discovered_at' => now(),
            ]);
        }

        app(SpiderPlannerService::class)->handleContactsCrawlCompleted(
            new ContactsCrawlCompleted(
                connectionKey: $connection->connection_key,
                subjectNsid: null,
                crawlRunId: $crawlRunId,
                spiderRunId: $run->id,
            ),
        );

        $this->assertDatabaseHas('spider_frontier_items', [
            'spider_run_id' => $run->id,
            'contact_nsid' => 'a@N01',
            'depth' => 0,
            'status' => SpiderFrontierStatus::Pending->value,
        ]);

        $run->refresh();
        $this->assertSame(2, $run->contacts_discovered);
    }

    public function test_contacts_crawl_completed_marks_frontier_item_crawled_and_enqueues_children(): void
    {
        $connection = $this->createFlickrConnection();

        $run = SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 2,
        ]);

        $item = SpiderFrontierItem::query()->create([
            'spider_run_id' => $run->id,
            'contact_nsid' => 'seed@N01',
            'depth' => 0,
            'status' => SpiderFrontierStatus::Queued,
        ]);

        $crawlRunId = 2;
        SubjectContact::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'seed@N01',
            'contact_nsid' => 'child@N02',
            'crawl_run_id' => $crawlRunId,
            'discovered_at' => now(),
        ]);

        app(SpiderPlannerService::class)->handleContactsCrawlCompleted(
            new ContactsCrawlCompleted(
                connectionKey: $connection->connection_key,
                subjectNsid: 'seed@N01',
                crawlRunId: $crawlRunId,
                spiderRunId: $run->id,
                spiderFrontierItemId: $item->id,
            ),
        );

        $item->refresh();
        $this->assertSame(SpiderFrontierStatus::Crawled, $item->status);
        $this->assertNotNull($item->crawled_at);

        $this->assertDatabaseHas('spider_frontier_items', [
            'spider_run_id' => $run->id,
            'contact_nsid' => 'child@N02',
            'depth' => 1,
            'status' => SpiderFrontierStatus::Pending->value,
        ]);
    }

    protected function tearDown(): void
    {
        if (app()->bound('config-store') && RuntimeConfig::has('spider.enabled')) {
            RuntimeConfig::forget('spider.enabled');
        }
        if (app()->bound('config-store') && RuntimeConfig::has('xflickr.global_pause')) {
            RuntimeConfig::forget('xflickr.global_pause');
        }
        if (app()->bound('config-store') && RuntimeConfig::has('spider.max_contacts_total')) {
            RuntimeConfig::forget('spider.max_contacts_total');
        }
        if (app()->bound('config-store')) {
            RuntimeConfig::refresh();
        }

        parent::tearDown();
    }

    public function test_start_creates_run_and_seeds_contacts_crawl_when_enabled(): void
    {
        Queue::fake();

        RuntimeConfig::set('spider.enabled', true, 'bool');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection();

        $run = app(SpiderPlannerService::class)->start($connection);

        $this->assertSame(SpiderRunStatus::Running, $run->status);
        $this->assertDatabaseHas('xflickr_crawl_runs', [
            'connection_key' => $connection->connection_key,
            'crawl_type' => 'contacts',
        ]);
    }

    public function test_status_includes_frontier_counts_for_latest_run(): void
    {
        RuntimeConfig::set('spider.enabled', true, 'bool');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection();
        $run = SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 2,
        ]);

        SpiderFrontierItem::query()->create([
            'spider_run_id' => $run->id,
            'contact_nsid' => 'pending@N01',
            'depth' => 0,
            'status' => SpiderFrontierStatus::Pending,
        ]);
        SpiderFrontierItem::query()->create([
            'spider_run_id' => $run->id,
            'contact_nsid' => 'queued@N02',
            'depth' => 0,
            'status' => SpiderFrontierStatus::Queued,
        ]);

        $status = app(SpiderPlannerService::class)->statusForConnection($connection->connection_key);

        $this->assertTrue($status['active']);
        $this->assertSame(1, $status['run']['pending']);
        $this->assertSame(1, $status['run']['queued']);
    }

    public function test_expand_run_queues_pending_frontier_items(): void
    {
        Queue::fake();

        RuntimeConfig::set('spider.enabled', true, 'bool');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection();
        $run = SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 2,
        ]);

        SpiderFrontierItem::query()->create([
            'spider_run_id' => $run->id,
            'contact_nsid' => 'frontier@N01',
            'depth' => 0,
            'status' => SpiderFrontierStatus::Pending,
        ]);

        $queued = app(SpiderPlannerService::class)->expandRun($run);

        $this->assertSame(1, $queued);
        $this->assertDatabaseHas('xflickr_crawl_runs', [
            'connection_key' => $connection->connection_key,
            'crawl_type' => 'photos',
            'subject_nsid' => 'frontier@N01',
        ]);
    }

    public function test_expand_active_runs_returns_zero_when_disabled(): void
    {
        RuntimeConfig::set('spider.enabled', false, 'bool');
        RuntimeConfig::refresh();

        $this->assertSame(0, app(SpiderPlannerService::class)->expandActiveRuns());
    }

    public function test_stop_returns_null_when_no_active_run(): void
    {
        $connection = $this->createFlickrConnection();

        $this->assertNull(app(SpiderPlannerService::class)->stop($connection));
    }

    public function test_start_refuses_when_full_pass_is_active(): void
    {
        RuntimeConfig::set('spider.enabled', true, 'bool');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection();

        ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('full contact pass is already active');

        app(SpiderPlannerService::class)->start($connection);
    }

    public function test_start_refuses_when_spider_run_already_active(): void
    {
        RuntimeConfig::set('spider.enabled', true, 'bool');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection();

        SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('spider run is already active');

        app(SpiderPlannerService::class)->start($connection);
    }

    public function test_expand_run_pauses_when_connection_is_missing(): void
    {
        $run = SpiderRun::query()->create([
            'connection_key' => FlickrNsid::fake(),
            'status' => SpiderRunStatus::Running,
            'max_depth' => 1,
        ]);

        $this->assertSame(0, app(SpiderPlannerService::class)->expandRun($run));

        $run->refresh();
        $this->assertSame(SpiderRunStatus::Paused, $run->status);
    }

    public function test_handle_contacts_crawl_completed_ignores_non_spider_events(): void
    {
        $connection = $this->createFlickrConnection();

        app(SpiderPlannerService::class)->handleContactsCrawlCompleted(
            new ContactsCrawlCompleted(
                connectionKey: $connection->connection_key,
                subjectNsid: null,
                crawlRunId: 1,
                discoveredContactNsids: [FlickrNsid::fake()],
                spiderRunId: null,
            ),
        );

        $this->assertSame(0, SpiderFrontierItem::query()->count());
    }

    public function test_start_refuses_when_global_pause_is_active(): void
    {
        RuntimeConfig::set('spider.enabled', true, 'bool');
        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection();

        $this->expectException(GlobalCrawlPauseException::class);

        app(SpiderPlannerService::class)->start($connection);
    }

    public function test_expand_active_runs_queues_pending_items_when_enabled(): void
    {
        Queue::fake();

        RuntimeConfig::set('spider.enabled', true, 'bool');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection();
        $run = SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 2,
        ]);

        SpiderFrontierItem::query()->create([
            'spider_run_id' => $run->id,
            'contact_nsid' => 'pending@N01',
            'depth' => 0,
            'status' => SpiderFrontierStatus::Pending,
        ]);

        $this->assertSame(1, app(SpiderPlannerService::class)->expandActiveRuns());
    }

    public function test_expand_run_completes_when_contact_cap_is_reached(): void
    {
        RuntimeConfig::set('spider.max_contacts_total', 1, 'int');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection();
        $run = SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'contacts_crawled' => 1,
            'max_depth' => 2,
        ]);

        $this->assertSame(0, app(SpiderPlannerService::class)->expandRun($run));

        $run->refresh();
        $this->assertSame(SpiderRunStatus::Completed, $run->status);
    }

    public function test_expand_run_maybe_completes_when_no_pending_frontier_items(): void
    {
        $connection = $this->createFlickrConnection();
        $run = SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 2,
        ]);

        SpiderFrontierItem::query()->create([
            'spider_run_id' => $run->id,
            'contact_nsid' => 'done@N01',
            'depth' => 0,
            'status' => SpiderFrontierStatus::Crawled,
        ]);

        $this->assertSame(0, app(SpiderPlannerService::class)->expandRun($run));

        $run->refresh();
        $this->assertSame(SpiderRunStatus::Completed, $run->status);
    }

    public function test_handle_contacts_crawl_completed_ignores_paused_spider_run(): void
    {
        $connection = $this->createFlickrConnection();
        $run = SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Paused,
            'max_depth' => 2,
        ]);

        app(SpiderPlannerService::class)->handleContactsCrawlCompleted(
            new ContactsCrawlCompleted(
                connectionKey: $connection->connection_key,
                subjectNsid: null,
                crawlRunId: 1,
                discoveredContactNsids: [FlickrNsid::fake()],
                spiderRunId: $run->id,
            ),
        );

        $this->assertSame(0, SpiderFrontierItem::query()->count());
    }
}

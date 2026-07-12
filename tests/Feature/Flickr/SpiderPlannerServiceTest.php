<?php

declare(strict_types=1);

namespace Tests\Feature\Flickr;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use JOOservices\XFlickrCrawler\Events\ContactsCrawlCompleted;
use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Models\SpiderFrontierItem;
use Modules\Spider\Models\SpiderRun;
use Modules\Spider\Services\SpiderPlannerService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
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
        $this->createFlickrConnection(['connection_key' => 'me@N01']);

        $status = app(SpiderPlannerService::class)->statusForConnection('me@N01');

        $this->assertFalse($status['active']);
        $this->assertNull($status['run']);
    }

    public function test_stop_pauses_active_run(): void
    {
        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

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
        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        $run = SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 2,
        ]);

        app(SpiderPlannerService::class)->handleContactsCrawlCompleted(
            new ContactsCrawlCompleted(
                connectionKey: $connection->connection_key,
                subjectNsid: null,
                crawlRunId: 1,
                discoveredContactNsids: ['a@N01', 'b@N02'],
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
        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

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

        app(SpiderPlannerService::class)->handleContactsCrawlCompleted(
            new ContactsCrawlCompleted(
                connectionKey: $connection->connection_key,
                subjectNsid: 'seed@N01',
                crawlRunId: 2,
                discoveredContactNsids: ['child@N02'],
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
            RuntimeConfig::refresh();
        }

        parent::tearDown();
    }
}

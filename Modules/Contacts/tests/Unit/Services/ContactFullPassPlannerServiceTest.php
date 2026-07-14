<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\ContactFactory;
use Illuminate\Support\Facades\Queue;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Contacts\Database\Factories\ContactFullPassFrontierItemFactory;
use Modules\Contacts\Database\Factories\ContactFullPassRunFactory;
use Modules\Contacts\Models\ContactFullPassFrontierItem;
use Modules\Contacts\Services\ContactFullPassPlannerService;
use Modules\Crawler\Events\ContactsCrawlCompleted;
use Modules\Flickr\Exceptions\GlobalCrawlPauseException;
use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Models\SpiderRun;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactFullPassPlannerServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    protected function tearDown(): void
    {
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

    public function test_start_creates_running_pass_and_seeds_contacts_crawl(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();

        $run = app(ContactFullPassPlannerService::class)->start($connection);

        $this->assertSame(SpiderRunStatus::Running, $run->status);
        $this->assertSame($connection->connection_key, $run->connection_key);
        $this->assertDatabaseHas('xflickr_crawl_runs', [
            'connection_key' => $connection->connection_key,
            'crawl_type' => 'contacts',
        ]);
    }

    public function test_start_refuses_when_global_pause_is_active(): void
    {
        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection();

        $this->expectException(GlobalCrawlPauseException::class);

        app(ContactFullPassPlannerService::class)->start($connection);
    }

    public function test_start_refuses_when_spider_run_is_active(): void
    {
        $connection = $this->createFlickrConnection();

        SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('spider run is already active');

        app(ContactFullPassPlannerService::class)->start($connection);
    }

    public function test_stop_pauses_active_pass(): void
    {
        $connection = $this->createFlickrConnection();
        ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
        ]);

        $run = app(ContactFullPassPlannerService::class)->stop($connection);

        $this->assertNotNull($run);
        $this->assertSame(SpiderRunStatus::Paused, $run->status);
        $this->assertNotNull($run->paused_at);
    }

    public function test_preview_reports_saved_contacts_and_active_state(): void
    {
        $connection = $this->createFlickrConnection();
        $contact = ContactFactory::new()->create();
        ConnectionContactFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
        ]);

        $run = ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
        ]);

        $preview = app(ContactFullPassPlannerService::class)->previewForConnection($connection);

        $this->assertTrue($preview['active']);
        $this->assertFalse($preview['spider_active']);
        $this->assertSame(1, $preview['saved_contacts_count']);
        $this->assertSame($run->id, $preview['run']['id']);
    }

    public function test_handle_contacts_crawl_completed_seeds_frontier_and_expands(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();
        $run = ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 1,
        ]);

        $discoveredNsid = FlickrNsid::fake();

        app(ContactFullPassPlannerService::class)->handleContactsCrawlCompleted(
            new ContactsCrawlCompleted(
                connectionKey: $connection->connection_key,
                subjectNsid: null,
                crawlRunId: 1,
                discoveredContactNsids: [$discoveredNsid],
                spiderRunId: null,
            ),
        );

        $this->assertDatabaseHas('contact_full_pass_frontier_items', [
            'contact_full_pass_run_id' => $run->id,
            'contact_nsid' => $discoveredNsid,
            'depth' => 0,
            'status' => SpiderFrontierStatus::Queued->value,
        ]);
    }

    public function test_expand_run_queues_catalog_crawls_for_pending_frontier(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();
        $run = ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 1,
        ]);

        ContactFullPassFrontierItemFactory::new()->create([
            'contact_full_pass_run_id' => $run->id,
            'contact_nsid' => $contactNsid,
            'depth' => 0,
            'status' => SpiderFrontierStatus::Pending,
        ]);

        $queued = app(ContactFullPassPlannerService::class)->expandRun($run);

        $this->assertSame(1, $queued);
        $this->assertDatabaseHas('xflickr_crawl_runs', [
            'connection_key' => $connection->connection_key,
            'crawl_type' => 'photos',
            'subject_nsid' => $contactNsid,
        ]);
    }

    public function test_expand_run_marks_leaf_items_crawled_at_max_depth(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();
        $run = ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 0,
        ]);

        $item = ContactFullPassFrontierItemFactory::new()->create([
            'contact_full_pass_run_id' => $run->id,
            'contact_nsid' => $contactNsid,
            'depth' => 0,
            'status' => SpiderFrontierStatus::Pending,
        ]);

        app(ContactFullPassPlannerService::class)->expandRun($run);

        $item->refresh();
        $this->assertSame(SpiderFrontierStatus::Crawled, $item->status);
        $this->assertNotNull($item->crawled_at);
    }

    public function test_start_refuses_when_full_pass_already_active(): void
    {
        $connection = $this->createFlickrConnection();

        ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('full contact pass is already active');

        app(ContactFullPassPlannerService::class)->start($connection);
    }

    public function test_stop_returns_null_when_no_active_pass(): void
    {
        $connection = $this->createFlickrConnection();

        $this->assertNull(app(ContactFullPassPlannerService::class)->stop($connection));
    }

    public function test_preview_returns_null_run_when_none_exist(): void
    {
        $connection = $this->createFlickrConnection();

        $preview = app(ContactFullPassPlannerService::class)->previewForConnection($connection);

        $this->assertFalse($preview['active']);
        $this->assertNull($preview['run']);
    }

    public function test_handle_contacts_crawl_completed_updates_frontier_item_depth(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();
        $subjectNsid = FlickrNsid::fake();
        $childNsid = FlickrNsid::fake();
        $run = ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 2,
        ]);

        $item = ContactFullPassFrontierItemFactory::new()->create([
            'contact_full_pass_run_id' => $run->id,
            'contact_nsid' => $subjectNsid,
            'depth' => 1,
            'status' => SpiderFrontierStatus::Queued,
        ]);

        app(ContactFullPassPlannerService::class)->handleContactsCrawlCompleted(
            new ContactsCrawlCompleted(
                connectionKey: $connection->connection_key,
                subjectNsid: $subjectNsid,
                crawlRunId: 3,
                discoveredContactNsids: [$childNsid],
                spiderRunId: null,
            ),
        );

        $item->refresh();
        $this->assertSame(SpiderFrontierStatus::Crawled, $item->status);
        $this->assertDatabaseHas('contact_full_pass_frontier_items', [
            'contact_full_pass_run_id' => $run->id,
            'contact_nsid' => $childNsid,
            'depth' => 2,
        ]);
    }

    public function test_expand_active_runs_returns_zero_when_global_pause_is_active(): void
    {
        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $this->assertSame(0, app(ContactFullPassPlannerService::class)->expandActiveRuns());
    }

    public function test_expand_active_runs_queues_pending_frontier_items(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();
        $run = ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 1,
        ]);

        ContactFullPassFrontierItemFactory::new()->create([
            'contact_full_pass_run_id' => $run->id,
            'contact_nsid' => FlickrNsid::fake(),
            'depth' => 0,
            'status' => SpiderFrontierStatus::Pending,
        ]);

        $this->assertSame(1, app(ContactFullPassPlannerService::class)->expandActiveRuns());
    }

    public function test_handle_contacts_crawl_completed_ignores_spider_events(): void
    {
        $connection = $this->createFlickrConnection();
        ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
        ]);

        app(ContactFullPassPlannerService::class)->handleContactsCrawlCompleted(
            new ContactsCrawlCompleted(
                connectionKey: $connection->connection_key,
                subjectNsid: null,
                crawlRunId: 1,
                discoveredContactNsids: [FlickrNsid::fake()],
                spiderRunId: 99,
            ),
        );

        $this->assertSame(0, ContactFullPassFrontierItem::query()->count());
    }

    public function test_expand_run_pauses_when_connection_is_missing(): void
    {
        $run = ContactFullPassRunFactory::new()->create([
            'connection_key' => FlickrNsid::fake(),
            'status' => SpiderRunStatus::Running,
            'max_depth' => 1,
        ]);

        $this->assertSame(0, app(ContactFullPassPlannerService::class)->expandRun($run));

        $run->refresh();
        $this->assertSame(SpiderRunStatus::Paused, $run->status);
    }

    public function test_expand_run_completes_when_contact_cap_is_reached(): void
    {
        RuntimeConfig::set('spider.max_contacts_total', 1, 'int');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection();
        $run = ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'contacts_crawled' => 1,
            'max_depth' => 1,
        ]);

        $this->assertSame(0, app(ContactFullPassPlannerService::class)->expandRun($run));

        $run->refresh();
        $this->assertSame(SpiderRunStatus::Completed, $run->status);
    }

    public function test_expand_run_maybe_completes_when_no_pending_frontier_items(): void
    {
        $connection = $this->createFlickrConnection();
        $run = ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 1,
        ]);

        ContactFullPassFrontierItemFactory::new()->create([
            'contact_full_pass_run_id' => $run->id,
            'contact_nsid' => FlickrNsid::fake(),
            'depth' => 0,
            'status' => SpiderFrontierStatus::Crawled,
        ]);

        $this->assertSame(0, app(ContactFullPassPlannerService::class)->expandRun($run));

        $run->refresh();
        $this->assertSame(SpiderRunStatus::Completed, $run->status);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Flickr;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use JOOservices\XFlickrCrawler\Events\ContactsCrawlCompleted;
use Modules\Contacts\Models\ContactFullPassRun;
use Modules\Contacts\Services\ContactFullPassPlannerService;
use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Models\SpiderRun;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class ContactFullPassPlannerServiceTest extends TestCase
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

    public function test_start_refuses_when_spider_run_is_active(): void
    {
        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 2,
        ]);

        $this->expectException(\RuntimeException::class);

        app(ContactFullPassPlannerService::class)->start($connection);
    }

    public function test_contacts_crawl_completed_seeds_frontier_at_depth_zero(): void
    {
        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        $run = ContactFullPassRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 1,
        ]);

        app(ContactFullPassPlannerService::class)->handleContactsCrawlCompleted(
            new ContactsCrawlCompleted(
                connectionKey: $connection->connection_key,
                subjectNsid: null,
                crawlRunId: 1,
                discoveredContactNsids: ['a@N01', 'b@N02'],
                spiderRunId: null,
            ),
        );

        $this->assertDatabaseHas('contact_full_pass_frontier_items', [
            'contact_full_pass_run_id' => $run->id,
            'contact_nsid' => 'a@N01',
            'depth' => 0,
            'status' => SpiderFrontierStatus::Queued->value,
        ]);

        $run->refresh();
        $this->assertSame(2, $run->contacts_discovered);
    }

    public function test_expand_preview_endpoint_returns_payload(): void
    {
        RuntimeConfig::set('spider.enabled', true, 'bool');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/expand-previews');

        $response->assertOk()
            ->assertJsonPath('data.account.nsid', 'me@N01')
            ->assertJsonPath('data.spider.enabled', true)
            ->assertJsonPath('data.full_pass.max_depth', 1);
    }
}

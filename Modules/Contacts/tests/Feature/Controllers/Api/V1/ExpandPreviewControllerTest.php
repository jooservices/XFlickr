<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Controllers\Api\V1;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Contacts\Models\ContactFullPassRun;
use Modules\Contacts\Services\ContactFullPassPlannerService;
use Modules\Crawler\Events\ContactsCrawlCompleted;
use Modules\Crawler\Models\SubjectContact;
use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Models\SpiderRun;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class ExpandPreviewControllerTest extends TestCase
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
        $connection = $this->createFlickrConnection();

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
        $connection = $this->createFlickrConnection();

        $run = ContactFullPassRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 1,
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

        app(ContactFullPassPlannerService::class)->handleContactsCrawlCompleted(
            new ContactsCrawlCompleted(
                connectionKey: $connection->connection_key,
                subjectNsid: null,
                crawlRunId: $crawlRunId,
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

        $connection = $this->createFlickrConnection();

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/expand-previews');

        $response->assertOk()
            ->assertJsonPath('data.account.nsid', $connection->connection_key)
            ->assertJsonPath('data.spider.enabled', true)
            ->assertJsonPath('data.spider.impact.crawl_targets_per_contact', 2)
            ->assertJsonPath('data.spider.impact.seed_crawl_targets', 1)
            ->assertJsonPath('data.full_pass.max_depth', 1);
    }
}

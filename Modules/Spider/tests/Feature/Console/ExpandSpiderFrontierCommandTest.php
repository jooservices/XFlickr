<?php

declare(strict_types=1);

namespace Modules\Spider\Tests\Feature\Console;

use Illuminate\Support\Facades\Queue;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Models\SpiderFrontierItem;
use Modules\Spider\Models\SpiderRun;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ExpandSpiderFrontierCommandTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_returns_success_without_output_when_spider_disabled(): void
    {
        RuntimeConfig::set('spider.enabled', false, 'bool');
        RuntimeConfig::refresh();

        $this->artisan('xflickr:spider:expand')
            ->assertSuccessful()
            ->doesntExpectOutputToContain('Queued');
    }

    public function test_reports_queued_count_when_frontier_contacts_are_expanded(): void
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
            'contact_nsid' => FlickrNsid::fake(),
            'depth' => 0,
            'status' => SpiderFrontierStatus::Pending,
        ]);

        $this->artisan('xflickr:spider:expand')
            ->expectsOutputToContain('Queued 1 spider frontier contact(s) for crawl.')
            ->assertSuccessful();
    }

    public function test_stays_silent_when_enabled_but_nothing_queued(): void
    {
        RuntimeConfig::set('spider.enabled', true, 'bool');
        RuntimeConfig::refresh();

        $this->artisan('xflickr:spider:expand')
            ->assertSuccessful()
            ->doesntExpectOutputToContain('Queued');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Services;

use Modules\Crawler\Enums\ApiOutcome;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\ApiLog;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Services\CrawlPruneService;
use Modules\Crawler\Tests\TestCase;
use Tests\Support\FlickrNsid;

final class CrawlPruneServiceTest extends TestCase
{
    private CrawlPruneService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CrawlPruneService::class);
    }

    public function test_prune_api_logs_deletes_rows_older_than_days(): void
    {
        $connectionKey = FlickrNsid::fake();

        ApiLog::query()->create([
            'connection_key' => $connectionKey,
            'api_method' => 'flickr.contacts.getList',
            'outcome' => ApiOutcome::Success,
            'created_at' => now()->subDays(40),
        ]);
        ApiLog::query()->create([
            'connection_key' => $connectionKey,
            'api_method' => 'flickr.contacts.getList',
            'outcome' => ApiOutcome::Success,
            'created_at' => now()->subDay(),
        ]);

        $deleted = $this->service->pruneApiLogsOlderThanDays(30);

        $this->assertSame(1, $deleted);
        $this->assertSame(1, ApiLog::query()->count());
    }

    public function test_prune_api_logs_clamps_days_below_one_to_one(): void
    {
        $connectionKey = FlickrNsid::fake();

        ApiLog::query()->create([
            'connection_key' => $connectionKey,
            'api_method' => 'flickr.contacts.getList',
            'outcome' => ApiOutcome::Success,
            'created_at' => now()->subHours(20),
        ]);
        ApiLog::query()->create([
            'connection_key' => $connectionKey,
            'api_method' => 'flickr.contacts.getList',
            'outcome' => ApiOutcome::Success,
            'created_at' => now()->subDays(2),
        ]);

        $deleted = $this->service->pruneApiLogsOlderThanDays(0);

        $this->assertSame(1, $deleted);
        $this->assertSame(1, ApiLog::query()->count());
    }

    public function test_prune_api_logs_returns_zero_when_empty(): void
    {
        $this->assertSame(0, $this->service->pruneApiLogsOlderThanDays(30));
    }

    public function test_prune_completed_targets_deletes_old_rows(): void
    {
        $run = CrawlRun::query()->create([
            'connection_key' => FlickrNsid::fake(),
            'crawl_type' => 'contacts',
            'status' => 'running',
            'started_at' => now(),
        ]);

        CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::ContactsPage,
            'page' => 1,
            'status' => CrawlStatus::Completed,
            'last_crawled_at' => now()->subDays(40),
        ]);
        CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::ContactsPage,
            'page' => 2,
            'status' => CrawlStatus::Completed,
            'last_crawled_at' => now()->subDay(),
        ]);

        $deleted = $this->service->pruneCompletedTargetsOlderThanDays(30);

        $this->assertSame(1, $deleted);
        $this->assertSame(1, CrawlTarget::query()->count());
    }
}

<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Enums\ApiOutcome;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\ApiLog;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Tests\TestCase;

final class ConsoleCommandsTest extends TestCase
{
    public function test_prune_command_deletes_old_api_logs(): void
    {
        ApiLog::query()->create([
            'connection_key' => 'prune-1',
            'api_method' => 'flickr.contacts.getList',
            'outcome' => ApiOutcome::Success,
            'created_at' => now()->subDays(40),
        ]);

        ApiLog::query()->create([
            'connection_key' => 'prune-1',
            'api_method' => 'flickr.contacts.getList',
            'outcome' => ApiOutcome::Success,
            'created_at' => now()->subDay(),
        ]);

        $exitCode = Artisan::call('xflickr:prune', ['--days' => 30]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, ApiLog::query()->count());
    }

    public function test_prune_command_can_delete_old_completed_targets(): void
    {
        $run = CrawlRun::query()->create([
            'connection_key' => 'prune-target',
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

        $exitCode = Artisan::call('xflickr:prune', ['--days' => 30, '--targets' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, CrawlTarget::query()->count());
    }

    public function test_doctor_command_passes_in_test_environment(): void
    {
        if (! $this->redisAvailable()) {
            $this->markTestSkipped('Redis is required for xflickr:doctor');
        }

        $exitCode = Artisan::call('xflickr:doctor');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('All XFlickr doctor checks passed', Artisan::output());
    }

    public function test_doctor_command_fails_when_app_profile_credentials_are_missing(): void
    {
        if (! $this->redisAvailable()) {
            $this->markTestSkipped('Redis is required for xflickr:doctor');
        }

        RuntimeConfig::forget('xflickr_app.default');
        RuntimeConfig::refresh();

        try {
            $exitCode = Artisan::call('xflickr:doctor');

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('[FAIL] app_profile', Artisan::output());
        } finally {
            RuntimeConfig::set('xflickr_app.default', [
                'apiKey' => 'test-api-key',
                'apiSecret' => 'test-api-secret',
            ], 'json');
            RuntimeConfig::refresh();
        }
    }

    private function redisAvailable(): bool
    {
        try {
            Redis::connection()->ping();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

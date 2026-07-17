<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Services;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use JOOservices\LaravelLogging\Jobs\StoreActivityLogJob;
use Modules\Crawler\Enums\ApiOutcome;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Events\CrawlRunFailed;
use Modules\Crawler\Events\CrawlRunStarted;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Services\FlickrApiAuditService;
use Modules\Crawler\Services\FlickrSpiderService;
use Modules\Crawler\Tests\TestCase;

final class FlickrSpiderServiceTest extends TestCase
{
    public function test_create_run_emits_started_event_and_domain_activity(): void
    {
        Event::fake([CrawlRunStarted::class]);
        Queue::fake();
        Log::spy();

        $spider = app(FlickrSpiderService::class);
        $connectionKey = 'create-run-'.fake()->uuid();

        $run = $spider->createRun($connectionKey, CrawlType::Contacts);

        Event::assertDispatched(CrawlRunStarted::class, fn (CrawlRunStarted $event): bool => $event->run->id === $run->id);
        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Crawler run started.'
                && ($context['run_id'] ?? null) === $run->id);
        Queue::assertPushedOn('logging', StoreActivityLogJob::class, fn (StoreActivityLogJob $job): bool => $job->data->action === 'crawler.run.started');
    }

    public function test_enqueue_specs_and_complete_run(): void
    {
        $spider = app(FlickrSpiderService::class);

        $run = CrawlRun::query()->create([
            'connection_key' => 'spider-1',
            'crawl_type' => CrawlType::Contacts->value,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        $target = $spider->enqueueTarget($run, TaskType::ContactsPage, null, null, 1);
        $this->assertSame(CrawlStatus::Pending, $target->status);

        $target->update(['status' => CrawlStatus::Completed]);
        $spider->maybeCompleteRun($run->fresh());

        $this->assertSame(CrawlRunStatus::Completed, $run->fresh()->status);
    }

    public function test_maybe_complete_run_when_all_targets_failed(): void
    {
        Event::fake([CrawlRunFailed::class]);
        Queue::fake();
        Log::spy();

        $spider = app(FlickrSpiderService::class);

        $run = CrawlRun::query()->create([
            'connection_key' => 'spider-failed',
            'crawl_type' => CrawlType::Photosets->value,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::PhotosetsList,
            'subject_nsid' => '999@N01',
            'page' => 1,
            'status' => CrawlStatus::Failed,
            'failed_reason' => 'API error',
        ]);

        $spider->maybeCompleteRun($run->fresh());

        $fresh = $run->fresh();
        $this->assertSame(CrawlRunStatus::Failed, $fresh->status);
        $this->assertNotNull($fresh->failed_reason);

        Event::assertDispatched(CrawlRunFailed::class, fn (CrawlRunFailed $event): bool => $event->run->id === $run->id
            && $event->reason === 'API error');
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message): bool => $message === 'Crawler run failed.');
        Queue::assertPushedOn('logging', StoreActivityLogJob::class, fn (StoreActivityLogJob $job): bool => $job->data->action === 'crawler.run.failed');
    }

    public function test_refresh_run_counters_sums_per_run_targets(): void
    {
        $spider = app(FlickrSpiderService::class);

        $runOne = CrawlRun::query()->create([
            'connection_key' => 'counter-1',
            'crawl_type' => CrawlType::Contacts->value,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $runOne->id,
            'task_type' => TaskType::ContactsPage,
            'page' => 1,
            'status' => CrawlStatus::Completed,
            'last_result_count' => 5,
        ]);

        $runTwo = CrawlRun::query()->create([
            'connection_key' => 'counter-1',
            'crawl_type' => CrawlType::Photos->value,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $runTwo->id,
            'task_type' => TaskType::PeoplePhotos,
            'subject_nsid' => '999@N01',
            'page' => 1,
            'status' => CrawlStatus::Completed,
            'last_result_count' => 12,
        ]);

        $spider->refreshRunCounters($runOne->fresh());
        $spider->refreshRunCounters($runTwo->fresh());

        $this->assertSame(5, $runOne->fresh()->contacts_discovered);
        $this->assertSame(0, $runOne->fresh()->photos_discovered);
        $this->assertSame(0, $runTwo->fresh()->contacts_discovered);
        $this->assertSame(12, $runTwo->fresh()->photos_discovered);
    }

    public function test_audit_service_logs_and_increments(): void
    {
        $audit = app(FlickrApiAuditService::class);
        $run = CrawlRun::query()->create([
            'connection_key' => 'audit-1',
            'crawl_type' => CrawlType::Contacts->value,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        $target = CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::ContactsPage,
            'page' => 1,
            'status' => CrawlStatus::Pending,
        ]);

        $log = $audit->log('audit-1', ApiOutcome::Success, 'flickr.contacts.getList', $target, 12);
        $audit->incrementApiCalls($run);

        $this->assertDatabaseHas('xflickr_api_logs', [
            'id' => $log->id,
            'api_method' => 'flickr.contacts.getList',
        ]);
        $this->assertSame(1, $run->fresh()->api_calls);
    }

    public function test_dispatch_due_targets_returns_zero_when_paused(): void
    {
        RuntimeConfig::set('xflickr.global_pause', true);
        RuntimeConfig::refresh();

        $spider = app(FlickrSpiderService::class);
        $this->assertSame(0, $spider->dispatchDueTargets());
    }

    public function test_enqueue_target_deduplicates_null_subject_keys(): void
    {
        $spider = app(FlickrSpiderService::class);

        $run = CrawlRun::query()->create([
            'connection_key' => 'dedupe-1',
            'crawl_type' => CrawlType::Contacts->value,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        $first = $spider->enqueueTarget($run, TaskType::ContactsPage, null, null, 1);
        $second = $spider->enqueueTarget($run, TaskType::ContactsPage, null, null, 1);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CrawlTarget::query()->where('xflickr_crawl_run_id', $run->id)->count());
        $this->assertSame('', $first->subject_nsid);
        $this->assertSame('', $first->subject_id);
    }
}

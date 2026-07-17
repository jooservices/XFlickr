<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Services;

use App\Support\ThirdPartyApiLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use JOOservices\LaravelLogging\Jobs\StoreActivityLogJob;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Services\CrawlerObservability;
use Modules\Crawler\Tests\TestCase;

final class CrawlerObservabilityTest extends TestCase
{
    public function test_run_failed_logs_fingerprint_not_raw_key_and_queues_domain_activity(): void
    {
        Log::spy();
        Queue::fake();

        $connectionKey = 'secret-connection-key-'.fake()->uuid();
        $run = CrawlRun::query()->create([
            'connection_key' => $connectionKey,
            'crawl_type' => CrawlType::Photos->value,
            'status' => CrawlRunStatus::Failed,
            'started_at' => now(),
            'failed_reason' => 'API error',
        ]);

        app(CrawlerObservability::class)->runFailed($run, 'API error');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($run, $connectionKey): bool {
                return $message === 'Crawler run failed.'
                    && ($context['run_id'] ?? null) === $run->id
                    && ($context['connection_key_fp'] ?? null) === ThirdPartyApiLogger::fingerprint($connectionKey)
                    && ! array_key_exists('connection_key', $context)
                    && ! in_array($connectionKey, $context, true);
            });

        Queue::assertPushedOn('logging', StoreActivityLogJob::class, function (StoreActivityLogJob $job) use ($run, $connectionKey): bool {
            $data = $job->data;

            return $data->action === 'crawler.run.failed'
                && $data->correlationId === (string) $run->id
                && ($data->properties['connection_key_fp'] ?? null) === ThirdPartyApiLogger::fingerprint($connectionKey)
                && ! array_key_exists('connection_key', $data->properties)
                && ! in_array($connectionKey, $data->properties, true);
        });
    }

    public function test_target_released_and_failed_include_ids_without_raw_key(): void
    {
        Log::spy();
        Queue::fake();

        $connectionKey = 'release-key-'.fake()->uuid();
        $run = CrawlRun::query()->create([
            'connection_key' => $connectionKey,
            'crawl_type' => CrawlType::Contacts->value,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        $target = CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::ContactsPage,
            'page' => 1,
            'status' => CrawlStatus::Processing,
            'claim_token' => (string) fake()->uuid(),
        ]);
        $target->setRelation('crawlRun', $run);

        $obs = app(CrawlerObservability::class);
        $obs->targetReleased($target, 60);
        $obs->targetFailed($target, 'timeout');

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($target, $run): bool {
                return $message === 'Crawler target released for retry.'
                    && ($context['target_id'] ?? null) === $target->id
                    && ($context['run_id'] ?? null) === $run->id
                    && ($context['retry_after_seconds'] ?? null) === 60
                    && ! array_key_exists('connection_key', $context);
            });

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($connectionKey): bool {
                return $message === 'Crawler target failed.'
                    && ($context['reason'] ?? null) === 'timeout'
                    && ($context['connection_key_fp'] ?? null) === ThirdPartyApiLogger::fingerprint($connectionKey);
            });

        Queue::assertPushed(StoreActivityLogJob::class, 2);
    }

    public function test_stalled_recovered_skips_zero_count(): void
    {
        Log::spy();
        Queue::fake();

        app(CrawlerObservability::class)->stalledRecovered(0);

        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('info');
        Queue::assertNothingPushed();
    }

    public function test_cooldown_triggered_fingerprints_connection_key(): void
    {
        Log::spy();
        Queue::fake();

        $connectionKey = 'cooldown-key-'.fake()->uuid();
        app(CrawlerObservability::class)->cooldownTriggered(3600, $connectionKey);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($connectionKey): bool {
                return $message === 'Crawler global cooldown triggered.'
                    && ($context['cooldown_seconds'] ?? null) === 3600
                    && ($context['connection_key_fp'] ?? null) === ThirdPartyApiLogger::fingerprint($connectionKey)
                    && ! array_key_exists('connection_key', $context);
            });

        Queue::assertPushedOn('logging', StoreActivityLogJob::class, function (StoreActivityLogJob $job) use ($connectionKey): bool {
            return $job->data->action === 'crawler.cooldown.triggered'
                && ($job->data->properties['connection_key_fp'] ?? null) === ThirdPartyApiLogger::fingerprint($connectionKey);
        });
    }
}

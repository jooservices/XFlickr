<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use App\Support\ThirdPartyApiLogger;
use Illuminate\Support\Facades\Log;
use JOOservices\LaravelLogging\Facades\ActivityLog;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;

/**
 * Single write API for crawler ops logs + durable domain activity.
 * Never logs raw connection keys, tokens, or response bodies.
 */
final class CrawlerObservability
{
    public function runStarted(CrawlRun $run): void
    {
        $context = $this->runContext($run);

        Log::info('Crawler run started.', $context);

        $this->recordDomain('crawler.run.started', $run, $context);
    }

    public function runCompleted(CrawlRun $run): void
    {
        $context = $this->runContext($run);

        Log::info('Crawler run completed.', $context);

        $this->recordDomain('crawler.run.completed', $run, $context);
    }

    public function runFailed(CrawlRun $run, string $reason): void
    {
        $context = [
            ...$this->runContext($run),
            'reason' => $reason,
        ];

        Log::warning('Crawler run failed.', $context);

        $this->recordDomain('crawler.run.failed', $run, $context);
    }

    public function targetReleased(CrawlTarget $target, int $retryAfterSeconds): void
    {
        $context = [
            ...$this->targetContext($target),
            'retry_after_seconds' => $retryAfterSeconds,
        ];

        Log::info('Crawler target released for retry.', $context);

        $this->recordDomain('crawler.target.released', $target->crawlRun, $context, $target);
    }

    public function targetFailed(CrawlTarget $target, string $reason): void
    {
        $context = [
            ...$this->targetContext($target),
            'reason' => $reason,
        ];

        Log::warning('Crawler target failed.', $context);

        $this->recordDomain('crawler.target.failed', $target->crawlRun, $context, $target);
    }

    public function stalledRecovered(int $count): void
    {
        if ($count <= 0) {
            return;
        }

        $context = ['count' => $count];

        Log::warning('Crawler stalled targets recovered.', $context);

        $this->recordDomain('crawler.targets.stalled_recovered', null, $context);
    }

    public function cooldownTriggered(int $seconds, ?string $connectionKey = null): void
    {
        $context = [
            'cooldown_seconds' => $seconds,
            'connection_key_fp' => ThirdPartyApiLogger::fingerprint($connectionKey),
        ];

        Log::warning('Crawler global cooldown triggered.', $context);

        $this->recordDomain('crawler.cooldown.triggered', null, $context);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function recordDomain(
        string $action,
        ?CrawlRun $run,
        array $properties,
        ?CrawlTarget $target = null,
    ): void {
        $log = ActivityLog::domain()
            ->action($action)
            ->bySystem()
            ->properties($properties)
            ->queue('logging');

        if ($run !== null) {
            $runId = (string) $run->id;
            $log->correlationId($runId)->batchId($runId)->onExternal('crawl_run', $run->id);

            if ($run->spider_run_id !== null) {
                $log->workflowId((string) $run->spider_run_id);
            }
        } elseif ($target !== null && $target->xflickr_crawl_run_id !== null) {
            $runId = (string) $target->xflickr_crawl_run_id;
            $log->correlationId($runId)->batchId($runId)->onExternal('crawl_run', $target->xflickr_crawl_run_id);
        }

        $log->dispatch();
    }

    /**
     * @return array<string, mixed>
     */
    private function runContext(CrawlRun $run): array
    {
        return [
            'run_id' => $run->id,
            'crawl_type' => $run->crawl_type,
            'connection_key_fp' => ThirdPartyApiLogger::fingerprint($run->connection_key),
            'spider_run_id' => $run->spider_run_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function targetContext(CrawlTarget $target): array
    {
        $run = $target->crawlRun;

        return [
            'target_id' => $target->id,
            'run_id' => $target->xflickr_crawl_run_id,
            'connection_key_fp' => $run === null ? null : ThirdPartyApiLogger::fingerprint($run->connection_key),
        ];
    }
}

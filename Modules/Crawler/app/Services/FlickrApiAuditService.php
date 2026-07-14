<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use Modules\Crawler\Enums\ApiOutcome;
use Modules\Crawler\Models\ApiLog;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Repositories\ApiLogRepository;
use Modules\Crawler\Repositories\CrawlRunRepository;

final class FlickrApiAuditService
{
    public function __construct(
        private readonly ApiLogRepository $apiLogs,
        private readonly CrawlRunRepository $crawlRuns,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $connectionKey,
        ApiOutcome $outcome,
        string $apiMethod,
        ?CrawlTarget $target = null,
        ?int $latencyMs = null,
        ?int $errorCode = null,
        ?string $errorMessage = null,
        array $context = [],
    ): ApiLog {
        return $this->apiLogs->create([
            'connection_key' => $connectionKey,
            'xflickr_crawl_run_id' => $target?->xflickr_crawl_run_id,
            'xflickr_crawl_target_id' => $target?->id,
            'api_method' => $apiMethod,
            'outcome' => $outcome,
            'latency_ms' => $latencyMs,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'context' => $context === [] ? null : $context,
            'created_at' => now(),
        ]);
    }

    public function incrementApiCalls(CrawlRun $run): void
    {
        $this->crawlRuns->incrementApiCalls($run);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Crawler\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\Flickr\Flickr;
use Modules\Crawler\Contracts\PageFetcherContract;
use Modules\Crawler\Jobs\Concerns\InteractsWithXFlickrCrawlJob;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Repositories\CrawlTargetRepository;
use Modules\Crawler\Services\FlickrApiAuditService;
use Modules\Crawler\Services\FlickrApiOutcomeClassifier;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Services\FlickrRequestLimiter;
use Modules\Crawler\Services\FlickrSpiderService;

abstract class AbstractXFlickrCrawlJob implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithXFlickrCrawlJob;
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(
        protected readonly int $crawlTargetId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->crawlTargetId;
    }

    protected function requiresSubjectNsid(): bool
    {
        return false;
    }

    protected function requiresSubjectId(): bool
    {
        return false;
    }

    protected function loadTarget(): ?CrawlTarget
    {
        return app(CrawlTargetRepository::class)->claimForProcessing($this->crawlTargetId);
    }

    protected function connectionKey(CrawlTarget $target): ?string
    {
        return $target->crawlRun?->connection_key;
    }

    protected function isTargetRunnable(CrawlTarget $target, ?string $connectionKey): bool
    {
        if ($target->crawlRun === null || $connectionKey === null) {
            return false;
        }

        if ($this->requiresSubjectNsid() && $target->subject_nsid === '') {
            return false;
        }

        if ($this->requiresSubjectId() && $target->subject_id === '') {
            return false;
        }

        return true;
    }

    /**
     * @param  callable(Flickr, CrawlTarget, PageFetcherContract): ApiResponseData  $callApi
     */
    protected function runWithPermit(
        FlickrClientFactory $clients,
        FlickrRequestLimiter $limiter,
        FlickrApiOutcomeClassifier $classifier,
        FlickrApiAuditService $audit,
        FlickrSpiderService $spider,
        PageFetcherContract $fetcher,
        string $apiMethod,
        callable $callApi,
    ): void {
        $target = $this->loadTarget();
        $connectionKey = $target !== null ? $this->connectionKey($target) : null;

        if ($target === null || ! $this->isTargetRunnable($target, $connectionKey)) {
            return;
        }

        $permit = $this->acquirePermit($limiter, $connectionKey);
        if (! $permit->acquired) {
            $this->releaseForPermit($target, $permit->retryAfterSeconds);

            return;
        }

        $this->executeCrawlPageJob(
            $target,
            $connectionKey,
            $apiMethod,
            $clients,
            $limiter,
            $classifier,
            $audit,
            $spider,
            $fetcher,
            $callApi,
        );
    }
}

<?php

declare(strict_types=1);

namespace Modules\Crawler\Jobs\Concerns;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\Flickr\Flickr;
use Modules\Crawler\Contracts\PageFetcherContract;
use Modules\Crawler\DTO\FetcherFetchResult;
use Modules\Crawler\DTO\FlickrPermit;
use Modules\Crawler\Enums\ApiOutcome;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Events\CrawlPageFailed;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Repositories\CrawlTargetRepository;
use Modules\Crawler\Services\FlickrApiAuditService;
use Modules\Crawler\Services\FlickrApiOutcomeClassifier;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Services\FlickrPermitAcquirer;
use Modules\Crawler\Services\FlickrRequestLimiter;
use Modules\Crawler\Services\FlickrSpiderService;
use Modules\Crawler\Support\FlickrResponseHelper;
use Modules\Crawler\Support\XFlickrConfig;
use Throwable;

trait InteractsWithXFlickrCrawlJob
{
    protected function targets(): CrawlTargetRepository
    {
        return app(CrawlTargetRepository::class);
    }

    protected function acquirePermit(FlickrRequestLimiter $limiter, string $connectionKey, int $maxAttempts = 120): FlickrPermit
    {
        return app(FlickrPermitAcquirer::class)->acquire($limiter, $connectionKey, $maxAttempts);
    }

    protected function handleApiResponse(
        string $connectionKey,
        ApiResponseData $response,
        CrawlTarget $target,
        string $apiMethod,
        int $latencyMs,
        FlickrApiOutcomeClassifier $classifier,
        FlickrApiAuditService $audit,
        FlickrRequestLimiter $limiter,
    ): bool {
        $outcome = $classifier->fromApiResponse($response);

        $audit->log(
            connectionKey: $connectionKey,
            outcome: $outcome,
            apiMethod: $apiMethod,
            target: $target,
            latencyMs: $latencyMs,
            errorCode: $response->error?->code,
            errorMessage: $response->error?->message,
        );

        if ($target->crawlRun !== null) {
            $audit->incrementApiCalls($target->crawlRun);
        }

        if ($outcome === ApiOutcome::RateLimited) {
            $limiter->triggerGlobalCooldown($connectionKey);
            $this->releaseTarget($target, XFlickrConfig::throttle('rate_limit_backoff_seconds', 3600));

            return false;
        }

        if (! $response->ok) {
            $this->failTarget($target, $response->error !== null ? $response->error->message : 'Flickr API error');

            return false;
        }

        return true;
    }

    protected function handleThrowable(
        string $connectionKey,
        Throwable $throwable,
        CrawlTarget $target,
        string $apiMethod,
        FlickrApiOutcomeClassifier $classifier,
        FlickrApiAuditService $audit,
        FlickrRequestLimiter $limiter,
    ): void {
        $outcome = $classifier->fromThrowable($throwable);

        $audit->log(
            connectionKey: $connectionKey,
            outcome: $outcome,
            apiMethod: $apiMethod,
            target: $target,
            errorMessage: $throwable->getMessage(),
        );

        if ($outcome === ApiOutcome::RateLimited) {
            $limiter->triggerGlobalCooldown($connectionKey);
            $this->releaseTarget($target, XFlickrConfig::throttle('rate_limit_backoff_seconds', 3600));

            return;
        }

        $this->failTarget($target, $throwable->getMessage());
    }

    protected function releaseForPermit(CrawlTarget $target, int $seconds): void
    {
        $this->releaseTarget($target, $seconds);
        $this->release($seconds);
    }

    protected function applyFollowUpSpecs(
        CrawlRun $run,
        FetcherFetchResult $result,
        FlickrSpiderService $spider,
    ): void {
        $spider->enqueueSpecs($run, $result->followUpSpecs);
    }

    protected function completeTarget(CrawlTarget $target, FlickrSpiderService $spider, int $resultCount = 0): void
    {
        $this->targets()->update($target, [
            'status' => CrawlStatus::Completed,
            'last_result_count' => $resultCount,
            'last_crawled_at' => now(),
            'locked_until' => null,
            'failed_reason' => null,
        ]);

        if ($target->crawlRun !== null) {
            $spider->dispatchPendingTargetsForRun($target->crawlRun);
            $spider->maybeCompleteRun($target->crawlRun);
            $spider->refreshRunCounters($target->crawlRun);
        }
    }

    protected function releaseTarget(CrawlTarget $target, int $seconds): void
    {
        $this->targets()->update($target, [
            'status' => CrawlStatus::Pending,
            'next_run_at' => now()->addSeconds($seconds),
            'locked_until' => null,
            'retry_count' => $target->retry_count + 1,
        ]);
    }

    protected function failTarget(CrawlTarget $target, string $reason): void
    {
        $target = $this->targets()->update($target, [
            'status' => CrawlStatus::Failed,
            'failed_reason' => $reason,
            'locked_until' => null,
            'last_crawled_at' => now(),
        ]);

        event(new CrawlPageFailed($target, $reason));

        if ($target->crawlRun !== null) {
            app(FlickrSpiderService::class)->maybeCompleteRun($target->crawlRun);
        }
    }

    protected function executeCrawlPageJob(
        CrawlTarget $target,
        string $connectionKey,
        string $apiMethod,
        FlickrClientFactory $clients,
        FlickrRequestLimiter $limiter,
        FlickrApiOutcomeClassifier $classifier,
        FlickrApiAuditService $audit,
        FlickrSpiderService $spider,
        PageFetcherContract $fetcher,
        callable $callApi,
    ): void {
        $response = $this->callFlickrApi(
            $target,
            $connectionKey,
            $apiMethod,
            $clients,
            $limiter,
            $classifier,
            $audit,
            $fetcher,
            $callApi,
        );

        if ($response === null) {
            return;
        }

        if ($this->shouldFailForInvalidAuthOnEmptyList($response, $apiMethod, $target, $clients, $connectionKey)) {
            $this->failTarget($target, 'auth_token_invalid');

            return;
        }

        try {
            $result = $fetcher->fetchPage($target, $response);
            $this->applyFollowUpSpecs($target->crawlRun, $result, $spider);
            $this->completeTarget($target, $spider, $result->resultCount);
        } catch (Throwable $throwable) {
            $this->failTarget($target, 'Persistence failed: '.$throwable->getMessage());
        }
    }

    /**
     * @param  callable(Flickr, CrawlTarget, PageFetcherContract): ApiResponseData  $callApi
     */
    private function callFlickrApi(
        CrawlTarget $target,
        string $connectionKey,
        string $apiMethod,
        FlickrClientFactory $clients,
        FlickrRequestLimiter $limiter,
        FlickrApiOutcomeClassifier $classifier,
        FlickrApiAuditService $audit,
        PageFetcherContract $fetcher,
        callable $callApi,
    ): ?ApiResponseData {
        $started = hrtime(true);

        try {
            $client = $clients->forConnection($connectionKey);
            $response = $callApi($client, $target, $fetcher);
            $latencyMs = (int) ((hrtime(true) - $started) / 1_000_000);

            if (! $this->handleApiResponse($connectionKey, $response, $target, $apiMethod, $latencyMs, $classifier, $audit, $limiter)) {
                return null;
            }

            return $response;
        } catch (Throwable $throwable) {
            $this->handleThrowable($connectionKey, $throwable, $target, $apiMethod, $classifier, $audit, $limiter);

            return null;
        }
    }

    private function shouldFailForInvalidAuthOnEmptyList(
        ApiResponseData $response,
        string $apiMethod,
        CrawlTarget $target,
        FlickrClientFactory $clients,
        string $connectionKey,
    ): bool {
        if ((int) $target->page !== 1) {
            return false;
        }

        if (! in_array($apiMethod, ['flickr.people.getPhotos', 'flickr.photosets.getList', 'flickr.galleries.getList'], true)) {
            return false;
        }

        $listKey = match ($apiMethod) {
            'flickr.people.getPhotos' => ['photos', 'photo'],
            'flickr.photosets.getList' => ['photosets', 'photoset'],
            default => ['galleries', 'gallery'],
        };

        $containerKey = $listKey[0];
        $total = (int) ($response->data[$containerKey]['total'] ?? 0);
        $parsed = count(FlickrResponseHelper::listItems($response->data ?? [], $listKey[0], $listKey[1]));

        if ($total > 0 || $parsed > 0) {
            return false;
        }

        $login = $clients->forConnection($connectionKey)->raw()->call('flickr.test.login', []);

        return ! $login->ok;
    }
}

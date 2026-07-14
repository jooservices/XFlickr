<?php

declare(strict_types=1);

namespace Modules\Crawler\Jobs;

use Modules\Crawler\Contracts\PageFetcherContract;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Services\FlickrApiAuditService;
use Modules\Crawler\Services\FlickrApiOutcomeClassifier;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Services\FlickrRequestLimiter;
use Modules\Crawler\Services\FlickrSpiderService;

final class FetchCrawlPageJob extends AbstractXFlickrCrawlJob
{
    private ?TaskType $taskType = null;

    public function handle(
        FlickrClientFactory $clients,
        FlickrRequestLimiter $limiter,
        FlickrApiOutcomeClassifier $classifier,
        FlickrApiAuditService $audit,
        FlickrSpiderService $spider,
    ): void {
        $this->taskType = $this->targets()->findWithRun($this->crawlTargetId)?->task_type;

        if ($this->taskType === null) {
            return;
        }

        /** @var PageFetcherContract $fetcher */
        $fetcher = app($this->taskType->fetcherClass());

        $this->runWithPermit(
            $clients,
            $limiter,
            $classifier,
            $audit,
            $spider,
            $fetcher,
            $this->taskType->apiMethod(),
            static fn ($client, $target, $fetcher) => $target->task_type->resolveParams($client, $target, $fetcher),
        );
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        $target = $this->targets()->findWithRun($this->crawlTargetId);

        if ($target === null) {
            return [];
        }

        $tags = ['crawl:'.$target->task_type->value];

        $connectionKey = $target->crawlRun?->connection_key;
        if ($connectionKey !== null) {
            $tags[] = 'connection:'.$connectionKey;
        }

        return $tags;
    }

    protected function requiresSubjectNsid(): bool
    {
        return $this->taskType?->requiresSubjectNsid() ?? false;
    }

    protected function requiresSubjectId(): bool
    {
        return $this->taskType?->requiresSubjectId() ?? false;
    }
}

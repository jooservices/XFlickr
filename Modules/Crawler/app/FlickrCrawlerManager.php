<?php

declare(strict_types=1);

namespace Modules\Crawler;

use Modules\Crawler\Services\ConnectionRegistryService;
use Modules\Crawler\Services\CrawlerCatalog;
use Modules\Crawler\Services\CrawlerRuns;
use Modules\Crawler\Services\CrawlingService;
use Modules\Crawler\Services\FlickrRequestLimiter;

final class FlickrCrawlerManager
{
    public function __construct(
        private readonly CrawlingService $crawling,
        private readonly FlickrRequestLimiter $limiter,
        private readonly CrawlerCatalog $catalog,
        private readonly CrawlerRuns $runs,
        private readonly ConnectionRegistryService $connections,
    ) {}

    public function connection(string $connectionKey, string $token, ?string $appProfile = null): FlickrConnection
    {
        return new FlickrConnection($connectionKey, $token, $appProfile, $this->crawling);
    }

    /**
     * @return array<string, mixed>
     */
    public function limiterState(string $connectionKey): array
    {
        return $this->limiter->state($connectionKey)->toArray();
    }

    public function catalog(): CrawlerCatalog
    {
        return $this->catalog;
    }

    public function runs(): CrawlerRuns
    {
        return $this->runs;
    }

    public function connections(): ConnectionRegistryService
    {
        return $this->connections;
    }
}

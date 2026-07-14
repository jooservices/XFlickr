<?php

declare(strict_types=1);

namespace Modules\Crawler\Jobs;

use Modules\Crawler\Contracts\PageFetcherContract;
use Modules\Crawler\Services\FlickrApiAuditService;
use Modules\Crawler\Services\FlickrApiOutcomeClassifier;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Services\FlickrRequestLimiter;
use Modules\Crawler\Services\FlickrSpiderService;

final class FetchContactsPageJob extends AbstractXFlickrCrawlJob
{
    public function handle(
        FlickrClientFactory $clients,
        FlickrRequestLimiter $limiter,
        FlickrApiOutcomeClassifier $classifier,
        FlickrApiAuditService $audit,
        FlickrSpiderService $spider,
        PageFetcherContract $fetcher,
    ): void {
        $this->runWithPermit(
            $clients,
            $limiter,
            $classifier,
            $audit,
            $spider,
            $fetcher,
            'flickr.contacts.getList',
            static fn ($client, $target, $fetcher) => $client->contacts()->getList([
                'per_page' => $fetcher->perPage(),
                'page' => $target->page,
            ]),
        );
    }
}

<?php

declare(strict_types=1);

namespace Modules\Crawler\Jobs;

use Modules\Crawler\Fetchers\SubjectContactsFetcher;
use Modules\Crawler\Services\FlickrApiAuditService;
use Modules\Crawler\Services\FlickrApiOutcomeClassifier;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Services\FlickrRequestLimiter;
use Modules\Crawler\Services\FlickrSpiderService;

final class FetchSubjectContactsPageJob extends AbstractXFlickrCrawlJob
{
    protected function requiresSubjectNsid(): bool
    {
        return true;
    }

    public function handle(
        FlickrClientFactory $clients,
        FlickrRequestLimiter $limiter,
        FlickrApiOutcomeClassifier $classifier,
        FlickrApiAuditService $audit,
        FlickrSpiderService $spider,
        SubjectContactsFetcher $fetcher,
    ): void {
        $this->runWithPermit(
            $clients,
            $limiter,
            $classifier,
            $audit,
            $spider,
            $fetcher,
            'flickr.contacts.getPublicList',
            static fn ($client, $target, $fetcher) => $client->contacts()->getPublicList([
                'user_id' => $target->subject_nsid,
                'per_page' => $fetcher->perPage(),
                'page' => $target->page,
            ]),
        );
    }
}

<?php

declare(strict_types=1);

namespace Modules\Crawler\Jobs;

use Modules\Crawler\Fetchers\PeoplePhotosFetcher;
use Modules\Crawler\Services\FlickrApiAuditService;
use Modules\Crawler\Services\FlickrApiOutcomeClassifier;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Services\FlickrRequestLimiter;
use Modules\Crawler\Services\FlickrSpiderService;
use Modules\Crawler\Support\FlickrCrawlQueryParams;

final class FetchPeoplePhotosJob extends AbstractXFlickrCrawlJob
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
        PeoplePhotosFetcher $fetcher,
    ): void {
        $this->runWithPermit(
            $clients,
            $limiter,
            $classifier,
            $audit,
            $spider,
            $fetcher,
            'flickr.people.getPhotos',
            static fn ($client, $target, $fetcher) => FlickrCrawlQueryParams::call(
                $client,
                'flickr.people.getPhotos',
                FlickrCrawlQueryParams::peoplePhotos($target->subject_nsid, $target->page, $fetcher->perPage()),
            ),
        );
    }
}

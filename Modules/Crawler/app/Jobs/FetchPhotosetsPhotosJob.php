<?php

declare(strict_types=1);

namespace Modules\Crawler\Jobs;

use Modules\Crawler\Fetchers\PhotosetsPhotosFetcher;
use Modules\Crawler\Services\FlickrApiAuditService;
use Modules\Crawler\Services\FlickrApiOutcomeClassifier;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Services\FlickrRequestLimiter;
use Modules\Crawler\Services\FlickrSpiderService;
use Modules\Crawler\Support\FlickrCrawlQueryParams;

final class FetchPhotosetsPhotosJob extends AbstractXFlickrCrawlJob
{
    protected function requiresSubjectId(): bool
    {
        return true;
    }

    public function handle(
        FlickrClientFactory $clients,
        FlickrRequestLimiter $limiter,
        FlickrApiOutcomeClassifier $classifier,
        FlickrApiAuditService $audit,
        FlickrSpiderService $spider,
        PhotosetsPhotosFetcher $fetcher,
    ): void {
        $this->runWithPermit(
            $clients,
            $limiter,
            $classifier,
            $audit,
            $spider,
            $fetcher,
            'flickr.photosets.getPhotos',
            static fn ($client, $target, $fetcher) => FlickrCrawlQueryParams::call(
                $client,
                'flickr.photosets.getPhotos',
                FlickrCrawlQueryParams::photosetPhotos($target->subject_id, $target->page, $fetcher->perPage()),
            ),
        );
    }
}

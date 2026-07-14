<?php

declare(strict_types=1);

namespace Modules\Crawler\Jobs;

use Modules\Crawler\Fetchers\GalleriesPhotosFetcher;
use Modules\Crawler\Services\FlickrApiAuditService;
use Modules\Crawler\Services\FlickrApiOutcomeClassifier;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Services\FlickrRequestLimiter;
use Modules\Crawler\Services\FlickrSpiderService;
use Modules\Crawler\Support\FlickrCrawlQueryParams;

final class FetchGalleriesPhotosJob extends AbstractXFlickrCrawlJob
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
        GalleriesPhotosFetcher $fetcher,
    ): void {
        $this->runWithPermit(
            $clients,
            $limiter,
            $classifier,
            $audit,
            $spider,
            $fetcher,
            'flickr.galleries.getPhotos',
            static fn ($client, $target, $fetcher) => FlickrCrawlQueryParams::call(
                $client,
                'flickr.galleries.getPhotos',
                FlickrCrawlQueryParams::galleryPhotos($target->subject_id, $target->page, $fetcher->perPage()),
            ),
        );
    }
}

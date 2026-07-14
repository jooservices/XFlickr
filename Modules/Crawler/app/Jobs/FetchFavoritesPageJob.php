<?php

declare(strict_types=1);

namespace Modules\Crawler\Jobs;

use Modules\Crawler\Fetchers\FavoritesFetcher;
use Modules\Crawler\Services\FlickrApiAuditService;
use Modules\Crawler\Services\FlickrApiOutcomeClassifier;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Services\FlickrRequestLimiter;
use Modules\Crawler\Services\FlickrSpiderService;
use Modules\Crawler\Support\FlickrCrawlQueryParams;

final class FetchFavoritesPageJob extends AbstractXFlickrCrawlJob
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
        FavoritesFetcher $fetcher,
    ): void {
        $this->runWithPermit(
            $clients,
            $limiter,
            $classifier,
            $audit,
            $spider,
            $fetcher,
            'flickr.favorites.getList',
            static fn ($client, $target, $fetcher) => FlickrCrawlQueryParams::call(
                $client,
                'flickr.favorites.getList',
                FlickrCrawlQueryParams::favoritesList($target->subject_nsid, $target->page, $fetcher->perPage()),
            ),
        );
    }
}

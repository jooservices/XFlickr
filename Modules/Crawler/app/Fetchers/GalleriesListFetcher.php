<?php

declare(strict_types=1);

namespace Modules\Crawler\Fetchers;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use Modules\Crawler\DTO\CrawlTaskSpec;
use Modules\Crawler\DTO\FetcherFetchResult;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Services\FlickrCatalogService;
use Modules\Crawler\Support\FlickrResponseHelper;

final class GalleriesListFetcher extends AbstractPageFetcher
{
    public function __construct(
        private readonly FlickrCatalogService $catalog,
    ) {}

    public function fetchPage(CrawlTarget $target, ApiResponseData $response): FetcherFetchResult
    {
        $galleries = FlickrResponseHelper::listItems($response->data, 'galleries', 'gallery');
        $ownerNsid = (string) ($target->subject_nsid ?? 'unknown');
        $count = $this->catalog->persistGalleries($galleries, $ownerNsid);

        $followUp = [];
        foreach ($galleries as $galleryData) {
            $galleryId = (string) ($galleryData['id'] ?? '');
            if ($galleryId === '') {
                continue;
            }

            $followUp[] = new CrawlTaskSpec(
                taskType: TaskType::GalleriesPhotos,
                subjectNsid: $ownerNsid,
                subjectId: $galleryId,
            );
        }

        $pagination = $response->pagination;
        if ($pagination !== null && $pagination->page < $pagination->pages) {
            $followUp[] = new CrawlTaskSpec(
                taskType: TaskType::GalleriesList,
                subjectNsid: $target->subject_nsid,
                page: $pagination->page + 1,
            );
        }

        return new FetcherFetchResult(
            resultCount: $count,
            followUpSpecs: $followUp,
        );
    }
}

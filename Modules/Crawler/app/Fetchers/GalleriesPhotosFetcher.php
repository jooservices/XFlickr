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

final class GalleriesPhotosFetcher extends AbstractPageFetcher
{
    public function __construct(
        private readonly FlickrCatalogService $catalog,
    ) {}

    public function fetchPage(CrawlTarget $target, ApiResponseData $response): FetcherFetchResult
    {
        $photos = FlickrResponseHelper::listItems($response->data, 'photos', 'photo');
        $ownerNsid = (string) ($target->subject_nsid ?? 'unknown');
        $galleryId = (string) $target->subject_id;
        $count = $this->catalog->persistGalleryPhotoPage($photos, $ownerNsid, $galleryId);

        $followUp = [];
        $pagination = $response->pagination;
        if ($pagination !== null && $pagination->page < $pagination->pages) {
            $followUp[] = new CrawlTaskSpec(
                taskType: TaskType::GalleriesPhotos,
                subjectNsid: $target->subject_nsid,
                subjectId: $target->subject_id,
                page: $pagination->page + 1,
            );
        }

        return new FetcherFetchResult(
            resultCount: $count,
            followUpSpecs: $followUp,
        );
    }
}

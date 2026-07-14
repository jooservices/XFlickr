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

final class PeoplePhotosFetcher extends AbstractPageFetcher
{
    public function __construct(
        private readonly FlickrCatalogService $catalog,
    ) {}

    public function fetchPage(CrawlTarget $target, ApiResponseData $response): FetcherFetchResult
    {
        $photos = FlickrResponseHelper::listItems($response->data, 'photos', 'photo');
        $subjectNsid = (string) $target->subject_nsid;
        $count = $this->catalog->persistPhotoPage($photos, $subjectNsid);

        $followUp = [];
        $pagination = $response->pagination;
        if ($pagination !== null && $pagination->page < $pagination->pages) {
            $followUp[] = new CrawlTaskSpec(
                taskType: TaskType::PeoplePhotos,
                subjectNsid: $subjectNsid,
                page: $pagination->page + 1,
            );
        }

        return new FetcherFetchResult(
            resultCount: $count,
            followUpSpecs: $followUp,
        );
    }
}

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

final class SubjectContactsFetcher extends AbstractPageFetcher
{
    public function __construct(
        private readonly FlickrCatalogService $catalog,
    ) {}

    public function fetchPage(CrawlTarget $target, ApiResponseData $response): FetcherFetchResult
    {
        $contacts = FlickrResponseHelper::listItems($response->data, 'contacts', 'contact');
        $connectionKey = (string) ($target->crawlRun->connection_key ?? '');
        $subjectNsid = (string) $target->subject_nsid;
        $crawlRunId = $target->crawlRun?->id;

        $count = $this->catalog->persistSubjectContacts(
            $contacts,
            $connectionKey,
            $subjectNsid,
            $crawlRunId,
        );

        $followUp = [];
        $pagination = $response->pagination;
        if ($pagination !== null && $pagination->page < $pagination->pages) {
            $followUp[] = new CrawlTaskSpec(
                taskType: TaskType::SubjectContactsPage,
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

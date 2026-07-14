<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Support;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use Modules\Crawler\DTO\FetcherFetchResult;
use Modules\Crawler\Fetchers\AbstractPageFetcher;
use Modules\Crawler\Models\CrawlTarget;
use RuntimeException;

final class FailingContactsFetcher extends AbstractPageFetcher
{
    public function fetchPage(CrawlTarget $target, ApiResponseData $response): FetcherFetchResult
    {
        throw new RuntimeException('DB write failed');
    }
}

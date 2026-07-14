<?php

declare(strict_types=1);

namespace Modules\Crawler\Contracts;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use Modules\Crawler\DTO\FetcherFetchResult;
use Modules\Crawler\Models\CrawlTarget;

interface PageFetcherContract
{
    public function fetchPage(CrawlTarget $target, ApiResponseData $response): FetcherFetchResult;

    public function perPage(): int;
}

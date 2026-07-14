<?php

declare(strict_types=1);

namespace Modules\Crawler\Fetchers;

use Modules\Crawler\Contracts\PageFetcherContract;
use Modules\Crawler\Support\XFlickrConfig;

abstract class AbstractPageFetcher implements PageFetcherContract
{
    public function perPage(): int
    {
        return XFlickrConfig::crawlInt('per_page', 500);
    }
}

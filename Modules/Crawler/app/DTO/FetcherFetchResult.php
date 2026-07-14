<?php

declare(strict_types=1);

namespace Modules\Crawler\DTO;

use JOOservices\Dto\Core\Dto;

final class FetcherFetchResult extends Dto
{
    /**
     * @param  list<CrawlTaskSpec>  $followUpSpecs
     */
    public function __construct(
        public readonly int $resultCount = 0,
        public readonly array $followUpSpecs = [],
    ) {}
}

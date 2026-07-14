<?php

declare(strict_types=1);

namespace Modules\Crawler\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Crawler\Models\CrawlTarget;

final class CrawlTargetJobFactory
{
    public function make(CrawlTarget $target): ShouldQueue
    {
        return new FetchCrawlPageJob($target->id);
    }
}

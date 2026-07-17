<?php

declare(strict_types=1);

namespace Modules\Crawler\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Crawler\Models\CrawlRun;

final class CrawlRunStarted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CrawlRun $run,
    ) {}
}

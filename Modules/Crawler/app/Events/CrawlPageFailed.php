<?php

declare(strict_types=1);

namespace Modules\Crawler\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Crawler\Models\CrawlTarget;

final class CrawlPageFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CrawlTarget $target,
        public readonly string $reason,
    ) {}
}

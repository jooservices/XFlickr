<?php

declare(strict_types=1);

namespace Modules\Crawler\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ContactsCrawlCompleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<string>  $discoveredContactNsids
     */
    public function __construct(
        public readonly string $connectionKey,
        public readonly ?string $subjectNsid,
        public readonly int $crawlRunId,
        public readonly array $discoveredContactNsids,
        public readonly ?int $spiderRunId = null,
        public readonly ?int $spiderFrontierItemId = null,
    ) {}
}

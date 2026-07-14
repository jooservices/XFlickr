<?php

declare(strict_types=1);

namespace Modules\Crawler\Enums;

enum CrawlStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}

<?php

declare(strict_types=1);

namespace Modules\Spider\Enums;

enum SpiderFrontierStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Crawled = 'crawled';
    case Skipped = 'skipped';
}

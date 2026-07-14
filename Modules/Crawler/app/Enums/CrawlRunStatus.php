<?php

declare(strict_types=1);

namespace Modules\Crawler\Enums;

enum CrawlRunStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}

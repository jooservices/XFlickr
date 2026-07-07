<?php

declare(strict_types=1);

namespace App\Enums;

enum SpiderRunStatus: string
{
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
}

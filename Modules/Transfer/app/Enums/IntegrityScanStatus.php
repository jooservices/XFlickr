<?php

declare(strict_types=1);

namespace Modules\Transfer\Enums;

enum IntegrityScanStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}

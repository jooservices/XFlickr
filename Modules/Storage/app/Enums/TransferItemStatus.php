<?php

declare(strict_types=1);

namespace Modules\Storage\Enums;

enum TransferItemStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}

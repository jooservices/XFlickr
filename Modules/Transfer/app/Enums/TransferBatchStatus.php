<?php

declare(strict_types=1);

namespace Modules\Transfer\Enums;

enum TransferBatchStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
}

<?php

declare(strict_types=1);

namespace Modules\Transfer\Enums;

enum TransferExecutionOutcome
{
    case Completed;
    case Deferred;
}

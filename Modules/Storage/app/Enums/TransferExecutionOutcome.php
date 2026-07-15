<?php

declare(strict_types=1);

namespace Modules\Storage\Enums;

enum TransferExecutionOutcome
{
    case Completed;
    case Deferred;
}

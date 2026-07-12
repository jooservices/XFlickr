<?php

declare(strict_types=1);

namespace Modules\Transfer\Enums;

enum PhotoTransferExecutionOutcome
{
    case Completed;
    case Deferred;
}

<?php

declare(strict_types=1);

namespace Modules\Transfer\Enums;

enum IntegrityAnomalyType: string
{
    case Orphaned = 'orphaned';
    case Missing = 'missing';
}

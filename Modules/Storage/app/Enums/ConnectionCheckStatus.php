<?php

declare(strict_types=1);

namespace Modules\Storage\Enums;

enum ConnectionCheckStatus: string
{
    case Passed = 'passed';
    case Warning = 'warning';
    case Failed = 'failed';
}

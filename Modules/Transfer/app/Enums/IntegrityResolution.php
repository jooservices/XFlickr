<?php

declare(strict_types=1);

namespace Modules\Transfer\Enums;

enum IntegrityResolution: string
{
    case Delete = 'delete';
    case Import = 'import';
    case Redownload = 'redownload';
}

<?php

declare(strict_types=1);

namespace Modules\Transfer\Enums;

enum TransferType: string
{
    case Download = 'download';
    case Upload = 'upload';
}

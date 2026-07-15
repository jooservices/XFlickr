<?php

declare(strict_types=1);

namespace Modules\Storage\Enums;

enum TransferType: string
{
    case Download = 'download';
    case Upload = 'upload';
}

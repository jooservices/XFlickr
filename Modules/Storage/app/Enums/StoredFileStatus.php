<?php

declare(strict_types=1);

namespace Modules\Storage\Enums;

enum StoredFileStatus: string
{
    case Pending = 'pending';
    case Downloading = 'downloading';
    case Completed = 'completed';
    case Failed = 'failed';
}

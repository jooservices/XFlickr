<?php

declare(strict_types=1);

namespace Modules\Storage\Enums;

enum StorageUploadStatus: string
{
    case Pending = 'pending';
    case Uploading = 'uploading';
    case Completed = 'completed';
    case Failed = 'failed';
}

<?php

declare(strict_types=1);

namespace Modules\Storage\Dto;

final readonly class StorageUploadRequest
{
    public function __construct(
        public string $localPath,
        public string $remotePath,
    ) {}
}

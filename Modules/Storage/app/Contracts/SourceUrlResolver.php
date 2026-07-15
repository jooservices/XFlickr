<?php

declare(strict_types=1);

namespace Modules\Storage\Contracts;

use Modules\Storage\Dto\ResolvedSource;
use Modules\Storage\Models\StoredFile;

interface SourceUrlResolver
{
    public function resolve(string $sourceType, string $sourceId, string $connectionKey): ResolvedSource;

    public function remoteUploadPath(StoredFile $storedFile): string;
}

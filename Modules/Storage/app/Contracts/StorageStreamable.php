<?php

declare(strict_types=1);

namespace Modules\Storage\Contracts;

use Modules\Storage\Dto\StorageStreamResult;
use Modules\Storage\Exceptions\StorageOperationException;

interface StorageStreamable
{
    /**
     * Open a read stream for HTTP streaming. Null when the object does not exist.
     *
     * @throws StorageOperationException on provider/IO failure
     */
    public function openStream(string $remotePath): ?StorageStreamResult;
}

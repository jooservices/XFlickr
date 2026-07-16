<?php

declare(strict_types=1);

namespace Modules\Storage\Exceptions;

use RuntimeException;
use Throwable;

final class StorageOperationException extends RuntimeException
{
    public static function fromProvider(string $provider, string $operation, Throwable $previous): self
    {
        return new self(
            "Storage provider [{$provider}] failed during [{$operation}].",
            previous: $previous,
        );
    }
}

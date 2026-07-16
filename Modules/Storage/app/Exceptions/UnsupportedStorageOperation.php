<?php

declare(strict_types=1);

namespace Modules\Storage\Exceptions;

use InvalidArgumentException;

final class UnsupportedStorageOperation extends InvalidArgumentException
{
    public static function for(string $provider, string $operation): self
    {
        return new self("Storage provider [{$provider}] does not support [{$operation}].");
    }
}

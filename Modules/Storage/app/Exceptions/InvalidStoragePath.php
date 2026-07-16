<?php

declare(strict_types=1);

namespace Modules\Storage\Exceptions;

use InvalidArgumentException;

final class InvalidStoragePath extends InvalidArgumentException
{
    public static function for(string $path): self
    {
        return new self("Invalid storage path [{$path}].");
    }
}

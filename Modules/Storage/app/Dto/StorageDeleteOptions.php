<?php

declare(strict_types=1);

namespace Modules\Storage\Dto;

final readonly class StorageDeleteOptions
{
    public function __construct(
        public ?string $containerId = null,
    ) {}
}

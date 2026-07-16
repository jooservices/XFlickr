<?php

declare(strict_types=1);

namespace Modules\Storage\Dto;

final readonly class StorageListOptions
{
    public function __construct(
        public int $perPage = 25,
        public ?string $albumPageToken = null,
        public ?string $itemPageToken = null,
        public ?string $containerId = null,
        public bool $includeAlbums = true,
        public bool $includeItems = true,
    ) {}
}

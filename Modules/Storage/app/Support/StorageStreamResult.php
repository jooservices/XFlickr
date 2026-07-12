<?php

declare(strict_types=1);

namespace Modules\Storage\Support;

final readonly class StorageStreamResult
{
    /**
     * @param  resource  $stream
     */
    public function __construct(
        public mixed $stream,
        public string $filename,
        public string $mimeType,
    ) {}
}

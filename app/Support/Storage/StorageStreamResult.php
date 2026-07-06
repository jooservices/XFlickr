<?php

declare(strict_types=1);

namespace App\Support\Storage;

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

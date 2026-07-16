<?php

declare(strict_types=1);

namespace Modules\Storage\Dto;

final class StorageUploadResult
{
    public function __construct(
        public readonly string $id,
        public readonly string $path,
        public readonly ?string $etag,
        public readonly ?string $name = null,
        public readonly ?string $mimeType = null,
        public readonly ?string $thumbnailUrl = null,
        public readonly ?string $modifiedAt = null,
    ) {}

    /**
     * @return array{id: string, path: string, etag: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'etag' => $this->etag,
        ];
    }
}

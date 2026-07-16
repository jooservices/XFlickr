<?php

declare(strict_types=1);

namespace Modules\Flickr\Dto;

final readonly class ResolvedPhotoSource
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $url,
        public string $destinationPath,
        public string $originalName,
        public string $variant,
        public array $metadata = [],
    ) {}
}

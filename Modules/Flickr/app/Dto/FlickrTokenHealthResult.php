<?php

declare(strict_types=1);

namespace Modules\Flickr\Dto;

final readonly class FlickrTokenHealthResult
{
    public function __construct(
        public bool $valid,
        public ?int $errorCode = null,
        public ?string $errorMessage = null,
        public ?string $userNsid = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Crawler\DTO;

use JOOservices\Dto\Core\Dto;

final class PhotoCountsDto extends Dto
{
    public function __construct(
        public readonly int $photos,
        public readonly int $photosets,
        public readonly int $galleries,
        public readonly int $favorites,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Flickr\Dto;

use JOOservices\Dto\Core\Data;

final class DownloadCandidateDto extends Data
{
    public function __construct(
        public string $url,
        public string $variant,
    ) {}
}

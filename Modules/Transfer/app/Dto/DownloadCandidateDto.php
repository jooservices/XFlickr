<?php

declare(strict_types=1);

namespace Modules\Transfer\Dto;

use JOOservices\Dto\Core\Data;

final class DownloadCandidateDto extends Data
{
    public function __construct(
        public string $url,
        public string $variant,
    ) {}
}

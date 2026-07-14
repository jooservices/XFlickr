<?php

declare(strict_types=1);

namespace Modules\Crawler\DTO;

use Carbon\CarbonImmutable;
use JOOservices\Dto\Core\Dto;

final class FlickrPermit extends Dto
{
    public function __construct(
        public readonly bool $acquired,
        public readonly int $retryAfterSeconds,
        public readonly ?CarbonImmutable $acquiredAt = null,
    ) {}
}

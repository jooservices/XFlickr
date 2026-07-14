<?php

declare(strict_types=1);

namespace Modules\Crawler\DTO;

use JOOservices\Dto\Core\Dto;

final class FlickrAppCredentialsDto extends Dto
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $apiSecret,
        public readonly ?string $label = null,
    ) {}
}

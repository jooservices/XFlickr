<?php

declare(strict_types=1);

namespace Modules\Crawler\DTO;

use JOOservices\Dto\Core\Dto;
use Modules\Crawler\Enums\TaskType;

final class CrawlTaskSpec extends Dto
{
    public function __construct(
        public readonly TaskType $taskType,
        public readonly ?string $subjectNsid = null,
        public readonly ?string $subjectId = null,
        public readonly int $page = 1,
        public readonly int $priority = 0,
    ) {}
}

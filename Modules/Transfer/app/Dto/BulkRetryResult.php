<?php

declare(strict_types=1);

namespace Modules\Transfer\Dto;

final readonly class BulkRetryResult
{
    /** @param list<array{source_id: string, reason: string}> $skipped */
    public function __construct(
        public int $queued,
        public array $skipped,
    ) {}
}

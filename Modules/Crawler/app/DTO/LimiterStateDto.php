<?php

declare(strict_types=1);

namespace Modules\Crawler\DTO;

use JOOservices\Dto\Core\Context;
use JOOservices\Dto\Core\Dto;

final class LimiterStateDto extends Dto
{
    public function __construct(
        public readonly string $connectionKey,
        public readonly int $maxRequestsPerHour,
        public readonly int $requestsUsed,
        public readonly int $requestsRemaining,
        public readonly int $minGapMs,
        public readonly bool $globalPause,
        public readonly ?string $globalPauseUntil,
        public readonly int $globalPauseSecondsRemaining,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(?Context $ctx = null): array
    {
        return [
            'connection_key' => $this->connectionKey,
            'max_requests_per_hour' => $this->maxRequestsPerHour,
            'requests_used' => $this->requestsUsed,
            'requests_remaining' => $this->requestsRemaining,
            'min_gap_ms' => $this->minGapMs,
            'global_pause' => $this->globalPause,
            'global_pause_until' => $this->globalPauseUntil,
            'global_pause_seconds_remaining' => $this->globalPauseSecondsRemaining,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Storage\Dto;

use Modules\Storage\Enums\ConnectionCheckStatus;

final class ConnectionReport
{
    /**
     * @param  list<ConnectionCheck>  $checks
     */
    public function __construct(
        public readonly int $accountId,
        public readonly string $accountLabel,
        public readonly string $provider,
        public readonly string $providerLabel,
        public readonly array $checks,
    ) {}

    public function healthy(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->status === ConnectionCheckStatus::Failed) {
                return false;
            }
        }

        return true;
    }
}

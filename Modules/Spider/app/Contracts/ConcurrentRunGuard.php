<?php

declare(strict_types=1);

namespace Modules\Spider\Contracts;

interface ConcurrentRunGuard
{
    public function hasActiveRun(string $connectionKey): bool;
}

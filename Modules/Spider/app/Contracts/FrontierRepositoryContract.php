<?php

declare(strict_types=1);

namespace Modules\Spider\Contracts;

use Modules\Spider\Enums\SpiderFrontierStatus;

interface FrontierRepositoryContract
{
    /**
     * @return list<string>
     */
    public function knownContactNsids(int $runId): array;

    public function enqueue(int $runId, string $contactNsid, int $depth): bool;

    public function countByStatus(int $runId, SpiderFrontierStatus $status): int;
}

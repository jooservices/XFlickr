<?php

declare(strict_types=1);

namespace Modules\Crawler\Repositories;

use Carbon\CarbonInterface;
use Modules\Crawler\Models\ApiLog;

final class ApiLogRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ApiLog
    {
        return ApiLog::query()->create($attributes);
    }

    public function deleteOlderThan(CarbonInterface $cutoff): int
    {
        return ApiLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}

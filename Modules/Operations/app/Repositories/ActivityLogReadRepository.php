<?php

declare(strict_types=1);

namespace Modules\Operations\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use JOOservices\LaravelLogging\ActivityLogQuery;
use JOOservices\LaravelLogging\Facades\ActivityLog;
use Modules\Operations\Dto\ActivityFeedFilter;

final class ActivityLogReadRepository
{
    /**
     * @return LengthAwarePaginator<int, mixed>
     */
    public function paginate(ActivityFeedFilter $filter): LengthAwarePaginator
    {
        request()->merge(['page' => $filter->page]);

        return $this->baseQuery($filter)->latest()->paginate($filter->perPage);
    }

    /**
     * @return array<string, int>
     */
    public function countByLevel(ActivityFeedFilter $filter): array
    {
        return $this->baseQuery($filter->withoutLevel())->countByLevel();
    }

    private function baseQuery(ActivityFeedFilter $filter): ActivityLogQuery
    {
        $query = ActivityLog::query();

        if ($filter->type !== null) {
            $query->type($filter->type);
        }

        if ($filter->level !== null) {
            $query->level($filter->level);
        }

        if ($filter->actionPrefix !== null) {
            $query->actionPrefix($filter->actionPrefix);
        }

        if ($filter->correlationId !== null) {
            $query->correlationId($filter->correlationId);
        }

        if ($filter->from !== null && $filter->to !== null) {
            $query->between($filter->from, $filter->to);
        } elseif ($filter->from !== null) {
            $query->since($filter->from);
        } elseif ($filter->to !== null) {
            $query->until($filter->to);
        }

        return $query;
    }
}

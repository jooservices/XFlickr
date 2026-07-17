<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Operations\Dto\ActivityFeedFilter;
use Modules\Operations\Repositories\ActivityLogReadRepository;

final class ActivityFeedService
{
    public function __construct(
        private readonly ActivityLogReadRepository $activityLogs,
    ) {}

    /**
     * @return array{
     *     paginator: LengthAwarePaginator<int, mixed>,
     *     facets: array{by_level: array<string, int>}
     * }
     */
    public function feed(ActivityFeedFilter $filter): array
    {
        return [
            'paginator' => $this->activityLogs->paginate($filter),
            'facets' => [
                'by_level' => $this->activityLogs->countByLevel($filter),
            ],
        ];
    }
}

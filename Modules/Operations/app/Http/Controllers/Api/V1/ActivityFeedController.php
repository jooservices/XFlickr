<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Operations\Http\Requests\Api\ActivityFeedIndexRequest;
use Modules\Operations\Http\Resources\ActivityLogResource;
use Modules\Operations\Services\ActivityFeedService;

final class ActivityFeedController extends BaseApiController
{
    public function __construct(
        private readonly ActivityFeedService $activityFeed,
    ) {}

    public function index(ActivityFeedIndexRequest $request): JsonResponse
    {
        $feed = $this->activityFeed->feed($request->toFilter());
        $paginator = $feed['paginator'];

        return $this->success(
            ActivityLogResource::collection($paginator),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'facets' => $feed['facets'],
            ],
        );
    }
}

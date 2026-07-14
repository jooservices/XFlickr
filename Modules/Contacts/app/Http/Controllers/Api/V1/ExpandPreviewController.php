<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Contacts\Http\Resources\ExpandPreviewResource;
use Modules\Contacts\Services\ContactFullPassPlannerService;
use Modules\Crawler\Models\Connection;
use Modules\Flickr\Support\ConnectionPresenter;
use Modules\Spider\Services\SpiderImpactEstimator;
use Modules\Spider\Services\SpiderPlannerService;
use Modules\Spider\Support\SpiderRuntimeConfig;

final class ExpandPreviewController extends BaseApiController
{
    public function show(
        Connection $connection,
        SpiderPlannerService $spiderPlanner,
        ContactFullPassPlannerService $fullPassPlanner,
        SpiderRuntimeConfig $spiderConfig,
        SpiderImpactEstimator $spiderImpact,
    ): JsonResponse {
        $spiderStatus = $spiderPlanner->statusForConnection($connection->connection_key);
        $fullPassPreview = $fullPassPlanner->previewForConnection($connection);
        $savedContactsCount = (int) ($fullPassPreview['saved_contacts_count'] ?? 0);

        return $this->success(ExpandPreviewResource::make([
            'account' => ConnectionPresenter::toArray($connection),
            'saved_contacts_count' => $savedContactsCount,
            'spider' => [
                'enabled' => $spiderConfig->enabled(),
                'max_depth' => $spiderConfig->maxDepth(),
                'max_new_contacts_per_run' => $spiderConfig->maxNewContactsPerRun(),
                'max_contacts_total' => $spiderConfig->maxContactsTotal(),
                'active' => (bool) ($spiderStatus['active'] ?? false),
                'run' => $spiderStatus['run'] ?? null,
                'impact' => $spiderImpact->estimate(
                    $spiderConfig->maxDepth(),
                    $spiderConfig->maxNewContactsPerRun(),
                    $spiderConfig->maxContactsTotal(),
                    $savedContactsCount,
                ),
            ],
            'full_pass' => $fullPassPreview,
        ]));
    }
}

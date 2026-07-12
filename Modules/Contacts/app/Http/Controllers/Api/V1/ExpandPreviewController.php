<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use JOOservices\XFlickrCrawler\Models\Connection;
use Modules\Contacts\Http\Resources\ExpandPreviewResource;
use Modules\Contacts\Services\ContactFullPassPlannerService;
use Modules\Flickr\Support\ConnectionPresenter;
use Modules\Spider\Services\SpiderPlannerService;
use Modules\Spider\Support\SpiderRuntimeConfig;

final class ExpandPreviewController extends BaseApiController
{
    public function show(
        Connection $connection,
        SpiderPlannerService $spiderPlanner,
        ContactFullPassPlannerService $fullPassPlanner,
        SpiderRuntimeConfig $spiderConfig,
    ): JsonResponse {
        $spiderStatus = $spiderPlanner->statusForConnection($connection->connection_key);
        $fullPassPreview = $fullPassPlanner->previewForConnection($connection);

        return $this->success(ExpandPreviewResource::make([
            'account' => ConnectionPresenter::toArray($connection),
            'saved_contacts_count' => $fullPassPreview['saved_contacts_count'] ?? 0,
            'spider' => [
                'enabled' => $spiderConfig->enabled(),
                'max_depth' => $spiderConfig->maxDepth(),
                'max_new_contacts_per_run' => $spiderConfig->maxNewContactsPerRun(),
                'max_contacts_total' => $spiderConfig->maxContactsTotal(),
                'active' => (bool) ($spiderStatus['active'] ?? false),
                'run' => $spiderStatus['run'] ?? null,
            ],
            'full_pass' => $fullPassPreview,
        ]));
    }
}

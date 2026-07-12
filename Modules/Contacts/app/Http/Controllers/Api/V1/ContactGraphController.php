<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use JOOservices\XFlickrCrawler\Models\Connection;
use Modules\Contacts\Http\Requests\Api\ContactGraphDeltaRequest;
use Modules\Contacts\Http\Requests\Api\ContactGraphExpandRequest;
use Modules\Contacts\Http\Requests\Api\ContactGraphSnapshotRequest;
use Modules\Contacts\Http\Resources\ContactGraphDeltaResource;
use Modules\Contacts\Http\Resources\ContactGraphExpandResource;
use Modules\Contacts\Http\Resources\ContactGraphSnapshotResource;
use Modules\Contacts\Services\ContactGraphExpandService;
use Modules\Contacts\Services\ContactGraphQueryService;
use Modules\Contacts\Support\ContactGraphRuntimeConfig;

final class ContactGraphController extends BaseApiController
{
    public function show(
        ContactGraphSnapshotRequest $request,
        Connection $connection,
        ContactGraphQueryService $graph,
        ContactGraphRuntimeConfig $graphConfig,
    ): JsonResponse {
        return $this->success(ContactGraphSnapshotResource::make($graph->snapshot(
            $connection,
            $request->directLimit($graphConfig),
        )));
    }

    public function delta(
        ContactGraphDeltaRequest $request,
        Connection $connection,
        ContactGraphQueryService $graph,
    ): JsonResponse {
        return $this->success(ContactGraphDeltaResource::make($graph->delta(
            $connection,
            $request->subjectNsid(),
            $request->sinceEdgeId(),
            $request->crawlRunId(),
        )));
    }

    public function expand(
        ContactGraphExpandRequest $request,
        Connection $connection,
        ContactGraphExpandService $expand,
    ): JsonResponse {
        return $this->success(ContactGraphExpandResource::make(
            $expand->expand($connection, $request->contactNsid()),
        ));
    }
}

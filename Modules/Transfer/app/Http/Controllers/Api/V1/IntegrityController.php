<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Transfer\Http\Requests\Api\FixIntegrityIssuesRequest;
use Modules\Transfer\Http\Requests\Api\StartIntegrityScanRequest;
use Modules\Transfer\Http\Resources\IntegrityAnomalyResource;
use Modules\Transfer\Http\Resources\IntegrityScanResource;
use Modules\Transfer\Models\IntegrityScan;
use Modules\Transfer\Services\IntegrityScanService;

final class IntegrityController extends BaseApiController
{
    public function __construct(private readonly IntegrityScanService $scans) {}

    public function store(StartIntegrityScanRequest $request): JsonResponse
    {
        return $this->accepted(IntegrityScanResource::make($this->scans->startScan()), 'Integrity scan queued.');
    }

    public function show(IntegrityScan $scan): JsonResponse
    {
        return $this->success(IntegrityScanResource::make($scan));
    }

    public function anomalies(IntegrityScan $scan): JsonResponse
    {
        return $this->success(IntegrityAnomalyResource::collection($this->scans->anomalies($scan, 50)));
    }

    public function resolve(FixIntegrityIssuesRequest $request, IntegrityScan $scan): JsonResponse
    {
        return $this->success($this->scans->resolve($scan, $request->resolution(), $request->anomalyIds()));
    }
}

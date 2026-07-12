<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers\Api\V1;

use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Operations\Services\OperationsStreamService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperationsStreamController extends BaseApiController
{
    public function __construct(
        private readonly OperationsStreamService $stream,
    ) {}

    public function stream(): StreamedResponse
    {
        return $this->stream->stream();
    }
}

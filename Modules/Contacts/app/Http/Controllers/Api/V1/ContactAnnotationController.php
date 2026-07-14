<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Contacts\Http\Requests\Api\UpdateContactAnnotationRequest;
use Modules\Contacts\Http\Resources\ContactAnnotationResource;
use Modules\Contacts\Services\ContactAnnotationService;
use Modules\Crawler\Models\Connection;

final class ContactAnnotationController extends BaseApiController
{
    public function update(
        UpdateContactAnnotationRequest $request,
        Connection $connection,
        string $contactNsid,
        ContactAnnotationService $annotations,
    ): JsonResponse {
        if (! $request->hasNoteUpdate() && ! $request->hasStarredUpdate()) {
            return $this->unprocessable('Provide note and/or starred in the request body.');
        }

        $annotation = $annotations->update(
            $connection->connection_key,
            $contactNsid,
            $request->hasNoteUpdate() ? $request->noteValue() : null,
            $request->hasStarredUpdate() ? $request->starredValue() : null,
        );

        return $this->success(ContactAnnotationResource::from($contactNsid, $annotation));
    }
}

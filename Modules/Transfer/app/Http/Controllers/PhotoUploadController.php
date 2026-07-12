<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use JOOservices\XFlickrCrawler\Models\Connection;
use Modules\Transfer\Http\Requests\QueuePhotoUploadRequest;
use Modules\Transfer\Services\PhotoUploadService;

final class PhotoUploadController
{
    public function __construct(
        private readonly PhotoUploadService $photoUploadService,
    ) {}

    public function store(QueuePhotoUploadRequest $request, Connection $connection): RedirectResponse
    {
        $result = $this->photoUploadService->queueFromInput(
            $connection,
            $request->storageAccountId(),
            $request->singlePhotoId(),
            $request->singleContactNsid(),
            $request->contactNsids(),
        );

        return back()->with($result->flashKey, $result->message);
    }
}

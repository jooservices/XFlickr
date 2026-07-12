<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use JOOservices\XFlickrCrawler\Models\Connection;
use Modules\Transfer\Http\Requests\QueuePhotoDownloadRequest;
use Modules\Transfer\Services\PhotoDownloadService;

final class PhotoDownloadController
{
    public function __construct(
        private readonly PhotoDownloadService $photoDownloadService,
    ) {}

    public function store(QueuePhotoDownloadRequest $request, Connection $connection): RedirectResponse
    {
        $result = $this->photoDownloadService->queueFromInput(
            $connection,
            $request->singlePhotoId(),
            $request->singleContactNsid(),
            $request->contactNsids(),
        );

        return back()->with($result->flashKey, $result->message);
    }
}

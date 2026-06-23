<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Transfer\QueuePhotoDownloadRequest;
use App\Services\Flickr\PhotoDownloadService;
use Illuminate\Http\RedirectResponse;
use JOOservices\XFlickrCrawler\Models\Connection;

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

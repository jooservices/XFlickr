<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Transfer\QueuePhotoUploadRequest;
use App\Services\Flickr\PhotoUploadService;
use Illuminate\Http\RedirectResponse;
use JOOservices\XFlickrCrawler\Models\Connection;

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

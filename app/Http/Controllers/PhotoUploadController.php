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
        $storageAccount = $this->photoUploadService->resolveStorageAccount($request->storageAccountId());

        if ($storageAccount === null) {
            return back()->with('error', 'No storage account configured.');
        }

        if ($flickrPhotoId = $request->singlePhotoId()) {
            $queued = $this->photoUploadService->queuePhotoUpload($connection, $storageAccount, $flickrPhotoId);

            if ($queued === 0) {
                return back()->with('success', 'No upload queued for this photo.');
            }

            return back()->with('success', 'Photo upload queued.');
        }

        $contactNsids = $request->contactNsids();

        if ($contactNsids !== []) {
            $queued = 0;

            foreach ($contactNsids as $contactNsid) {
                $queued += $this->photoUploadService->queueUploads($connection, $storageAccount, $contactNsid);
            }

            if ($queued === 0) {
                return back()->with('success', 'No photos pending upload.');
            }

            $contactCount = count($contactNsids);

            return back()->with('success', "{$queued} photo(s) queued for upload across {$contactCount} contact(s).");
        }

        if ($contactNsid = $request->singleContactNsid()) {
            $queued = $this->photoUploadService->queueUploads($connection, $storageAccount, $contactNsid);

            if ($queued === 0) {
                return back()->with('success', 'No photos pending upload.');
            }

            return back()->with('success', "{$queued} photo(s) queued for upload.");
        }

        $queued = $this->photoUploadService->queueUploads($connection, $storageAccount);

        if ($queued === 0) {
            return back()->with('success', 'No photos pending upload.');
        }

        return back()->with('success', 'Account photo upload queued.');
    }
}

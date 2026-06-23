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
        if ($flickrPhotoId = $request->singlePhotoId()) {
            $queuedBatches = $this->photoDownloadService->queuePhotoDownload($connection, $flickrPhotoId);

            if ($queuedBatches === 0) {
                return back()->with('success', 'No download queued for this photo.');
            }

            return back()->with('success', 'Photo download queued.');
        }

        $contactNsids = $request->contactNsids();

        if ($contactNsids !== []) {
            $queuedBatches = 0;

            foreach ($contactNsids as $contactNsid) {
                $queuedBatches += $this->photoDownloadService->queueDownloads($connection, $contactNsid);
            }

            if ($queuedBatches === 0) {
                return back()->with('success', 'No photos pending download.');
            }

            return back()->with('success', "{$queuedBatches} contact download batch(es) queued.");
        }

        if ($contactNsid = $request->singleContactNsid()) {
            $queuedBatches = $this->photoDownloadService->queueDownloads($connection, $contactNsid);

            if ($queuedBatches === 0) {
                return back()->with('success', 'No photos pending download.');
            }

            return back()->with('success', "{$queuedBatches} download batch(es) queued.");
        }

        $queuedBatches = $this->photoDownloadService->queueDownloads($connection);

        if ($queuedBatches === 0) {
            return back()->with('success', 'No photos pending download.');
        }

        return back()->with('success', "{$queuedBatches} download batch(es) queued.");
    }
}

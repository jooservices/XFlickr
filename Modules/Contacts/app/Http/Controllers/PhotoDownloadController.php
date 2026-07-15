<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Modules\Contacts\Http\Requests\QueuePhotoDownloadRequest;
use Modules\Contacts\Services\ContactListQueryService;
use Modules\Crawler\Models\Connection;
use Modules\Flickr\Services\FlickrAccountsService;
use Modules\Storage\Dto\TransferQueueResult;

final class PhotoDownloadController
{
    public function __construct(
        private readonly FlickrAccountsService $flickr,
        private readonly ContactListQueryService $contactList,
    ) {}

    public function store(QueuePhotoDownloadRequest $request, Connection $connection): RedirectResponse
    {
        if ($request->wantsSelectAll()) {
            $result = $this->queueSelectAll($request, $connection);

            return back()->with($result->flashKey, $result->message);
        }

        $result = $this->flickr->queueDownloads(
            $connection,
            $request->singlePhotoId(),
            $request->singleContactNsid(),
            $request->contactNsids(),
            $request->flickrPhotoIds(),
        );

        return back()->with($result->flashKey, $result->message);
    }

    private function queueSelectAll(QueuePhotoDownloadRequest $request, Connection $connection): TransferQueueResult
    {
        $ownerNsid = $request->bulkOwnerNsid();

        if ($ownerNsid !== null) {
            return $this->flickr->queueDownloads(
                $connection,
                contactNsid: $ownerNsid,
            );
        }

        $contactNsids = $this->contactList->listNsidsForConnection(
            $connection,
            $request->bulkSearch(),
            $request->bulkStarredOnly(),
        );

        if ($contactNsids === []) {
            return TransferQueueResult::error('No contacts matched the current filters.');
        }

        return $this->flickr->queueDownloads(
            $connection,
            contactNsids: $contactNsids,
        );
    }
}

<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Modules\Contacts\Http\Requests\QueuePhotoDownloadRequest;
use Modules\Contacts\Services\ContactListQueryService;
use Modules\Crawler\Models\Connection;
use Modules\Transfer\Dto\TransferQueueResult;
use Modules\Transfer\Services\PhotoTransferService;

final class PhotoDownloadController
{
    public function __construct(
        private readonly PhotoTransferService $transfers,
        private readonly ContactListQueryService $contactList,
    ) {}

    public function store(QueuePhotoDownloadRequest $request, Connection $connection): RedirectResponse
    {
        if ($request->wantsSelectAll()) {
            $result = $this->queueSelectAll($request, $connection);

            return back()->with($result->flashKey, $result->message);
        }

        $result = $this->transfers->queueDownloadsFromInput(
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
            return $this->transfers->queueDownloadsFromInput(
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

        return $this->transfers->queueDownloadsFromInput(
            $connection,
            contactNsids: $contactNsids,
        );
    }
}

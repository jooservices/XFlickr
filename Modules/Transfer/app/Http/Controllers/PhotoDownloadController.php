<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Modules\Contacts\Services\ContactListQueryService;
use Modules\Crawler\Models\Connection;
use Modules\Transfer\Http\Requests\QueuePhotoDownloadRequest;
use Modules\Transfer\Services\PhotoDownloadService;
use Modules\Transfer\Services\TransferQueueResult;

final class PhotoDownloadController
{
    public function __construct(
        private readonly PhotoDownloadService $photoDownloadService,
        private readonly ContactListQueryService $contactList,
    ) {}

    public function store(QueuePhotoDownloadRequest $request, Connection $connection): RedirectResponse
    {
        if ($request->wantsSelectAll()) {
            $result = $this->queueSelectAll($request, $connection);

            return back()->with($result->flashKey, $result->message);
        }

        $result = $this->photoDownloadService->queueFromInput(
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
            return $this->photoDownloadService->queueFromInput(
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

        return $this->photoDownloadService->queueFromInput(
            $connection,
            contactNsids: $contactNsids,
        );
    }
}

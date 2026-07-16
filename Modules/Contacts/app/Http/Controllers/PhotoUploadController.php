<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Modules\Contacts\Http\Requests\QueuePhotoUploadRequest;
use Modules\Contacts\Services\ContactListQueryService;
use Modules\Crawler\Models\Connection;
use Modules\Transfer\Dto\TransferQueueResult;
use Modules\Transfer\Services\PhotoTransferService;

final class PhotoUploadController
{
    public function __construct(
        private readonly PhotoTransferService $transfers,
        private readonly ContactListQueryService $contactList,
    ) {}

    public function store(QueuePhotoUploadRequest $request, Connection $connection): RedirectResponse
    {
        if ($request->wantsSelectAll()) {
            $result = $this->queueSelectAll($request, $connection);

            return back()->with($result->flashKey, $result->message);
        }

        $result = $this->transfers->queueUploadsFromInput(
            $connection,
            $request->storageAccountId(),
            $request->singlePhotoId(),
            $request->singleContactNsid(),
            $request->contactNsids(),
            $request->flickrPhotoIds(),
            $request->deleteLocalAfterUpload(),
        );

        return back()->with($result->flashKey, $result->message);
    }

    private function queueSelectAll(QueuePhotoUploadRequest $request, Connection $connection): TransferQueueResult
    {
        $ownerNsid = $request->bulkOwnerNsid();
        $deleteLocal = $request->deleteLocalAfterUpload();

        if ($ownerNsid !== null) {
            return $this->transfers->queueUploadsFromInput(
                $connection,
                storageAccountId: $request->storageAccountId(),
                contactNsid: $ownerNsid,
                deleteLocalAfterUpload: $deleteLocal,
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

        return $this->transfers->queueUploadsFromInput(
            $connection,
            storageAccountId: $request->storageAccountId(),
            contactNsids: $contactNsids,
            deleteLocalAfterUpload: $deleteLocal,
        );
    }
}

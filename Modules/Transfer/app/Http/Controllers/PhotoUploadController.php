<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Modules\Contacts\Services\ContactListQueryService;
use Modules\Crawler\Models\Connection;
use Modules\Transfer\Http\Requests\QueuePhotoUploadRequest;
use Modules\Transfer\Services\PhotoUploadService;
use Modules\Transfer\Services\TransferQueueResult;

final class PhotoUploadController
{
    public function __construct(
        private readonly PhotoUploadService $photoUploadService,
        private readonly ContactListQueryService $contactList,
    ) {}

    public function store(QueuePhotoUploadRequest $request, Connection $connection): RedirectResponse
    {
        if ($request->wantsSelectAll()) {
            $result = $this->queueSelectAll($request, $connection);

            return back()->with($result->flashKey, $result->message);
        }

        $result = $this->photoUploadService->queueFromInput(
            $connection,
            $request->storageAccountId(),
            $request->singlePhotoId(),
            $request->singleContactNsid(),
            $request->contactNsids(),
            $request->flickrPhotoIds(),
        );

        return back()->with($result->flashKey, $result->message);
    }

    private function queueSelectAll(QueuePhotoUploadRequest $request, Connection $connection): TransferQueueResult
    {
        $ownerNsid = $request->bulkOwnerNsid();

        if ($ownerNsid !== null) {
            return $this->photoUploadService->queueFromInput(
                $connection,
                storageAccountId: $request->storageAccountId(),
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

        return $this->photoUploadService->queueFromInput(
            $connection,
            storageAccountId: $request->storageAccountId(),
            contactNsids: $contactNsids,
        );
    }
}

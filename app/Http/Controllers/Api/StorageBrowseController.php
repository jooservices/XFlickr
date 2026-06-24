<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\StorageDownloadStreamer;
use App\Enums\StorageDriver;
use App\Http\Requests\Api\Storage\BrowseStorageRequest;
use App\Http\Requests\Api\Storage\DeleteStorageItemsRequest;
use App\Http\Requests\Api\Storage\DownloadStorageFileRequest;
use App\Http\Requests\Api\Storage\GooglePhotosThumbnailRequest;
use App\Http\Requests\Api\Storage\ListStorageAccountsRequest;
use App\Http\Requests\Api\Storage\SyncStorageRequest;
use App\Models\StorageAccount;
use App\Services\Storage\GooglePhotosThumbnailService;
use App\Services\Storage\StorageAccountScopeService;
use App\Services\Storage\StorageBrowseLocalService;
use App\Services\Storage\StorageBrowseService;
use App\Services\Storage\StorageBrowseSyncService;
use App\Services\Storage\StorageDeleteService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class StorageBrowseController
{
    public function accounts(ListStorageAccountsRequest $request, StorageBrowseService $browse): JsonResponse
    {
        return response()->json([
            'data' => $browse->accountsForProvider($request->driver()),
        ]);
    }

    public function browse(
        BrowseStorageRequest $request,
        string $provider,
        StorageBrowseService $browse,
        StorageBrowseLocalService $local,
        StorageAccountScopeService $scopes,
    ): JsonResponse {
        try {
            $driver = StorageDriver::fromRouteSlug($provider);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'Invalid storage provider.'], 422);
        }

        $account = $request->account($driver);

        if ($scopes->needsReauthorization($account)) {
            return $this->reauthorizationResponse($scopes, $account);
        }

        $perPage = $request->perPage();
        $containerId = $request->containerId();

        try {
            if ($request->source() === 'provider') {
                $result = $browse->browse(
                    $driver,
                    $account->id,
                    $perPage,
                    $request->albumPageToken(),
                    $request->itemPageToken(),
                    $containerId,
                );
            } else {
                $result = $local->browse($account, $containerId, $request->albumPage(), $request->itemPage(), $perPage);
            }
        } catch (Throwable $e) {
            return $this->browseErrorResponse($e, $scopes, $account);
        }

        return response()->json($result->toArray($perPage));
    }

    public function sync(
        SyncStorageRequest $request,
        string $provider,
        StorageBrowseSyncService $sync,
        StorageAccountScopeService $scopes,
    ): JsonResponse {
        try {
            $driver = StorageDriver::fromRouteSlug($provider);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'Invalid storage provider.'], 422);
        }

        $account = $request->account($driver);

        if ($scopes->needsReauthorization($account)) {
            return $this->reauthorizationResponse($scopes, $account);
        }

        try {
            if ($request->shouldReconcile()) {
                $sync->reconcile($account, $request->containerId());
            }

            $result = $sync->sync($account, $driver, $request->containerId(), $request->maxBatches());
        } catch (Throwable $e) {
            return $this->browseErrorResponse($e, $scopes, $account);
        }

        return response()->json(['data' => $result]);
    }

    public function delete(
        DeleteStorageItemsRequest $request,
        string $provider,
        StorageDeleteService $delete,
        StorageAccountScopeService $scopes,
    ): JsonResponse {
        try {
            $driver = StorageDriver::fromRouteSlug($provider);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'Invalid storage provider.'], 422);
        }

        $account = $request->account($driver);

        if ($scopes->needsReauthorization($account)) {
            return $this->reauthorizationResponse($scopes, $account);
        }

        try {
            $result = $delete->deleteMany(
                $account,
                $driver,
                $request->itemIds(),
                $request->containerId(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->browseErrorResponse($e, $scopes, $account);
        }

        return response()->json(['data' => $result]);
    }

    public function googlePhotosThumbnail(
        GooglePhotosThumbnailRequest $request,
        GooglePhotosThumbnailService $thumbnails,
    ): StreamedResponse|JsonResponse {
        try {
            return $thumbnails->stream($request->accountId(), $request->mediaId());
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function download(
        DownloadStorageFileRequest $request,
        string $provider,
        StorageAccountScopeService $scopes,
        StorageDownloadStreamer $downloads,
    ): StreamedResponse|JsonResponse {
        try {
            $driver = StorageDriver::fromRouteSlug($provider);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'Invalid storage provider.'], 422);
        }

        $account = $request->account($driver);

        if ($scopes->needsReauthorization($account)) {
            return $this->reauthorizationResponse($scopes, $account);
        }

        $remotePath = $request->path();

        try {
            $stream = $downloads->openStreamForAccount($account, $remotePath);
            if ($stream === null) {
                return response()->json(['message' => 'Remote file not found.'], 404);
            }

            return response()->stream(function () use ($stream): void {
                fpassthru($stream->stream);

                if (is_resource($stream->stream)) {
                    fclose($stream->stream);
                }
            }, 200, [
                'Content-Type' => $stream->mimeType,
                'Content-Disposition' => $this->downloadDisposition($stream->filename),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function reauthorizationResponse(StorageAccountScopeService $scopes, StorageAccount $account): JsonResponse
    {
        return response()->json([
            'message' => 'Additional permissions are required. Please reauthorize this storage account.',
            'needs_reauthorization' => true,
            ...$scopes->authorizationMeta($account),
        ], 403);
    }

    private function browseErrorResponse(Throwable $e, StorageAccountScopeService $scopes, StorageAccount $account): JsonResponse
    {
        $message = $e->getMessage();
        $needsReauth = str_contains(strtoupper($message), 'PERMISSION_DENIED')
            || str_contains(strtolower($message), 'insufficient');

        if ($needsReauth) {
            return response()->json([
                'message' => 'This account is missing required permissions. Please reauthorize.',
                'needs_reauthorization' => true,
                ...$scopes->authorizationMeta($account),
            ], 403);
        }

        return response()->json(['message' => $message], 422);
    }

    private function downloadDisposition(string $filename): string
    {
        $filename = str_replace(["\0", '/', '\\'], '_', $filename);
        $fallback = preg_replace('/[^\x20-\x7e]|[%\/\\\\"]/', '_', $filename) ?: 'download';
        $fallback = trim($fallback);

        return HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename !== '' ? $filename : 'download',
            $fallback !== '' ? $fallback : 'download',
        );
    }
}

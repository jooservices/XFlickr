<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Storage\Contracts\StorageDownloadStreamer;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Http\Requests\Api\Storage\BrowseStorageRequest;
use Modules\Storage\Http\Requests\Api\Storage\DeleteStorageItemsRequest;
use Modules\Storage\Http\Requests\Api\Storage\DownloadStorageFileRequest;
use Modules\Storage\Http\Requests\Api\Storage\GooglePhotosThumbnailRequest;
use Modules\Storage\Http\Requests\Api\Storage\ListStorageAccountsRequest;
use Modules\Storage\Http\Requests\Api\Storage\SyncStorageRequest;
use Modules\Storage\Http\Resources\StorageAccountResource;
use Modules\Storage\Http\Resources\StorageBrowseResource;
use Modules\Storage\Http\Resources\StorageDeleteResultResource;
use Modules\Storage\Http\Resources\StorageReauthorizationResource;
use Modules\Storage\Http\Resources\StorageSyncResultResource;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\GooglePhotos\ThumbnailService;
use Modules\Storage\Services\StorageAccountScopeService;
use Modules\Storage\Services\StorageBrowseLocalService;
use Modules\Storage\Services\StorageBrowseService;
use Modules\Storage\Services\StorageBrowseSyncService;
use Modules\Storage\Services\StorageDeleteService;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class StorageBrowseController extends BaseApiController
{
    public function accounts(ListStorageAccountsRequest $request, StorageBrowseService $browse): JsonResponse
    {
        return $this->success(StorageAccountResource::collection($browse->accountsForProvider($request->driver())));
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
            return $this->unprocessable('Invalid storage provider.');
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

        $payload = $result->toArray($perPage);

        return $this->success(
            StorageBrowseResource::make([
                'albums' => $payload['albums'],
                'items' => $payload['items'],
            ]),
            meta: is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
        );
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
            return $this->unprocessable('Invalid storage provider.');
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

        return $this->success(StorageSyncResultResource::make($result));
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
            return $this->unprocessable('Invalid storage provider.');
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
            return $this->unprocessable($e->getMessage());
        } catch (Throwable $e) {
            return $this->browseErrorResponse($e, $scopes, $account);
        }

        return $this->success(StorageDeleteResultResource::make($result));
    }

    public function googlePhotosThumbnail(
        GooglePhotosThumbnailRequest $request,
        ThumbnailService $thumbnails,
    ): StreamedResponse|JsonResponse {
        try {
            return $thumbnails->stream($request->accountId(), $request->mediaId());
        } catch (Throwable $e) {
            return $this->storageErrorResponse($e, null, 'Unable to load thumbnail.');
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
            return $this->unprocessable('Invalid storage provider.');
        }

        $account = $request->account($driver);

        if ($scopes->needsReauthorization($account)) {
            return $this->reauthorizationResponse($scopes, $account);
        }

        $remotePath = $request->path();

        try {
            $stream = $downloads->openStreamForAccount($account, $remotePath);
            if ($stream === null) {
                return $this->notFound('Remote file not found.');
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
            return $this->unprocessable($e->getMessage());
        } catch (Throwable $e) {
            return $this->storageErrorResponse($e, $account, 'Unable to download file.');
        }
    }

    private function reauthorizationResponse(StorageAccountScopeService $scopes, StorageAccount $account): JsonResponse
    {
        return $this->formatResponse(
            false,
            403,
            'Additional permissions are required. Please reauthorize this storage account.',
            StorageReauthorizationResource::make([
                'needs_reauthorization' => true,
                ...$scopes->authorizationMeta($account),
            ]),
        );
    }

    private function browseErrorResponse(Throwable $e, StorageAccountScopeService $scopes, StorageAccount $account): JsonResponse
    {
        return $this->storageErrorResponse($e, $account, 'Unable to browse storage.', $scopes);
    }

    private function storageErrorResponse(
        Throwable $e,
        ?StorageAccount $account,
        string $clientMessage,
        ?StorageAccountScopeService $scopes = null,
    ): JsonResponse {
        Log::error('Storage API request failed.', [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'account_id' => $account?->id,
            'provider' => $account?->provider,
        ]);

        $message = $e->getMessage();
        $needsReauth = str_contains(strtoupper($message), 'PERMISSION_DENIED')
            || str_contains(strtolower($message), 'insufficient');

        if ($needsReauth && $scopes !== null && $account !== null) {
            return $this->formatResponse(
                false,
                403,
                'This account is missing required permissions. Please reauthorize.',
                StorageReauthorizationResource::make([
                    'needs_reauthorization' => true,
                    ...$scopes->authorizationMeta($account),
                ]),
            );
        }

        return $this->unprocessable($clientMessage);
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

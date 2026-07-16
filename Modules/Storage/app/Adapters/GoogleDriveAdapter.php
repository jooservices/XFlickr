<?php

declare(strict_types=1);

namespace Modules\Storage\Adapters;

use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Number;
use Modules\Storage\Contracts\StorageVerifiable;
use Modules\Storage\Dto\ConnectionCheck;
use Modules\Storage\Dto\ConnectionReport;
use Modules\Storage\Dto\StorageBrowseResult;
use Modules\Storage\Dto\StorageDeleteOptions;
use Modules\Storage\Dto\StorageListOptions;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\OAuthConnectionChecks;
use Modules\Storage\Services\StorageFlysystemFactory;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use Modules\Storage\Support\StorageApiLogger;
use RuntimeException;
use Throwable;

final class GoogleDriveAdapter extends AbstractFlysystemAdapter implements StorageVerifiable
{
    public function __construct(
        StorageAccount $account,
        StorageFlysystemFactory $flysystem,
        private readonly GoogleTokenService $tokens,
        private readonly OAuthConnectionChecks $oauthChecks,
        private readonly StorageApiLogger $apiLogger,
    ) {
        parent::__construct($account, $flysystem);
    }

    public function delete(array $itemIds, ?StorageDeleteOptions $options = null): array
    {
        $credentials = $this->account->credentials ?? [];
        $client = $this->tokens->clientForAccount($credentials, $this->account);
        $service = new Drive($client);

        $deleted = [];
        $failed = [];

        foreach ($itemIds as $itemId) {
            try {
                $service->files->delete($itemId);
                $deleted[] = $itemId;
            } catch (Throwable $e) {
                $failed[] = [
                    'id' => $itemId,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }

    public function list(?StorageListOptions $options = null): StorageBrowseResult
    {
        $options ??= new StorageListOptions;
        $credentials = $this->account->credentials ?? [];
        $client = $this->tokens->clientForAccount($credentials, $this->account);
        $service = new Drive($client);

        $parentQuery = $options->containerId !== null && $options->containerId !== ''
            ? "'{$options->containerId}' in parents"
            : "'root' in parents";

        $albumResult = $options->includeAlbums
            ? $this->listFolderAlbums($service, $parentQuery, $options->perPage, $options->albumPageToken)
            : ['albums' => [], 'nextPageToken' => null];

        $itemResult = $options->includeItems
            ? $this->listImageItems($service, $parentQuery, $options->perPage, $options->itemPageToken)
            : ['items' => [], 'nextPageToken' => null];

        return new StorageBrowseResult(
            albums: $albumResult['albums'],
            items: $itemResult['items'],
            albumNextPageToken: $albumResult['nextPageToken'],
            itemNextPageToken: $itemResult['nextPageToken'],
        );
    }

    public function verify(): ConnectionReport
    {
        $driver = StorageDriver::GoogleDrive;

        if ($this->account->provider !== $driver->value) {
            throw new RuntimeException('Account is not a Google Drive storage account.');
        }

        $authorization = $this->oauthChecks->authorization($this->account);
        $checks = [
            $this->oauthChecks->oauthApp($this->account, $driver),
            $authorization,
        ];

        if ($this->oauthChecks->probeAllowed($authorization)) {
            $checks[] = $this->driveCheck();
        }

        return new ConnectionReport(
            accountId: $this->account->id,
            accountLabel: $this->account->label,
            provider: $driver->value,
            providerLabel: $driver->label(),
            checks: $checks,
        );
    }

    // ---- Browse internals ----

    /**
     * @return array{albums: list<array<string, mixed>>, nextPageToken: string|null}
     */
    private function listFolderAlbums(
        Drive $service,
        string $parentQuery,
        int $perPage,
        ?string $albumPageToken,
    ): array {
        $folderResponse = $service->files->listFiles([
            'q' => "{$parentQuery} and mimeType='application/vnd.google-apps.folder' and trashed=false",
            'fields' => 'nextPageToken, files(id, name, modifiedTime, thumbnailLink)',
            'pageSize' => min($perPage, 100),
            'pageToken' => $albumPageToken,
            'spaces' => 'drive',
            'orderBy' => 'name',
        ]);

        $albums = [];
        foreach ($folderResponse->getFiles() ?? [] as $file) {
            if (! $file instanceof DriveFile) {
                continue;
            }

            $albums[] = [
                'id' => (string) $file->getId(),
                'title' => (string) ($file->getName() ?? 'Untitled folder'),
                'cover_thumbnail_url' => $file->getThumbnailLink(),
                'media_items_count' => null,
            ];
        }

        $folderNext = $folderResponse->getNextPageToken();

        return [
            'albums' => $albums,
            'nextPageToken' => $folderNext !== null ? (string) $folderNext : null,
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, nextPageToken: string|null}
     */
    private function listImageItems(
        Drive $service,
        string $parentQuery,
        int $perPage,
        ?string $itemPageToken,
    ): array {
        $imageQuery = "{$parentQuery} and mimeType contains 'image/' and trashed=false";

        $fileResponse = $service->files->listFiles([
            'q' => $imageQuery,
            'fields' => 'nextPageToken, files(id, name, mimeType, thumbnailLink, size, modifiedTime, webViewLink)',
            'pageSize' => min($perPage, 100),
            'pageToken' => $itemPageToken,
            'spaces' => 'drive',
            'orderBy' => 'modifiedTime desc',
        ]);

        $items = [];
        foreach ($fileResponse->getFiles() ?? [] as $file) {
            if (! $file instanceof DriveFile) {
                continue;
            }

            $items[] = [
                'id' => (string) $file->getId(),
                'name' => (string) ($file->getName() ?? 'Untitled'),
                'mime_type' => $file->getMimeType(),
                'thumbnail_url' => $file->getThumbnailLink(),
                'size' => $file->getSize() !== null ? (int) $file->getSize() : null,
                'modified_at' => $file->getModifiedTime(),
                'path' => null,
                'web_url' => $file->getWebViewLink(),
            ];
        }

        $fileNext = $fileResponse->getNextPageToken();

        return [
            'items' => $items,
            'nextPageToken' => $fileNext !== null ? (string) $fileNext : null,
        ];
    }

    // ---- Verify internals ----

    private function driveCheck(): ConnectionCheck
    {
        try {
            $accessToken = $this->accessToken();
        } catch (RuntimeException $e) {
            return ConnectionCheck::failed('Drive API', 'Drive probe failed: '.$e->getMessage());
        }

        $endpoint = 'https://www.googleapis.com/drive/v3/about';
        $startedAt = microtime(true);
        $response = Http::withToken($accessToken)->timeout(30)->get($endpoint, [
            'fields' => 'user(displayName,emailAddress),storageQuota(limit,usage)',
        ]);
        $this->apiLogger->logRequest('google', 'GET', $endpoint, $startedAt, $response, null, ['account_id' => $this->account->id]);

        if (! $response->successful()) {
            $message = $response->json('error.message');

            return ConnectionCheck::failed(
                'Drive API',
                'Drive probe failed: '.(is_string($message) && $message !== '' ? $message : 'Google Drive about request failed.'),
            );
        }

        return ConnectionCheck::passed('Drive API', 'Google Drive API is reachable.', $this->driveDetails($response->json()));
    }

    private function accessToken(): string
    {
        return $this->tokens->accessToken($this->account->credentials ?? [], $this->account);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return list<string>
     */
    private function driveDetails(?array $payload): array
    {
        $payload ??= [];
        $details = [];

        $user = is_array($payload['user'] ?? null) ? $payload['user'] : [];
        $name = trim((string) ($user['displayName'] ?? ''));
        $email = trim((string) ($user['emailAddress'] ?? ''));
        if ($name !== '' || $email !== '') {
            $details[] = trim("Connected as: {$name} ".($email !== '' ? "<{$email}>" : ''));
        }

        $quota = is_array($payload['storageQuota'] ?? null) ? $payload['storageQuota'] : [];
        if (isset($quota['usage'])) {
            $usage = Number::fileSize((int) $quota['usage']);
            $details[] = isset($quota['limit'])
                ? "Storage used: {$usage} of ".Number::fileSize((int) $quota['limit'])
                : "Storage used: {$usage} (no quota limit)";
        }

        return $details;
    }
}

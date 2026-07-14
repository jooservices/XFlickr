<?php

declare(strict_types=1);

namespace Modules\Storage\Services\GoogleDrive;

use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Modules\Storage\Contracts\StorageBrowseDriver;
use Modules\Storage\Dto\StorageBrowseResult;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\Tokens\GoogleTokenService;

final class BrowseService implements StorageBrowseDriver
{
    public function __construct(
        private readonly GoogleTokenService $tokens,
    ) {}

    public function browse(
        StorageAccount $account,
        int $perPage,
        ?string $albumPageToken,
        ?string $itemPageToken,
        ?string $containerId,
        bool $includeAlbums,
        bool $includeItems,
    ): StorageBrowseResult {
        $credentials = $account->credentials ?? [];
        $client = $this->tokens->clientForAccount($credentials, $account);
        $service = new Drive($client);

        $parentQuery = $containerId !== null && $containerId !== ''
            ? "'{$containerId}' in parents"
            : "'root' in parents";

        $albumResult = $includeAlbums
            ? $this->listFolderAlbums($service, $parentQuery, $perPage, $albumPageToken)
            : ['albums' => [], 'nextPageToken' => null];

        $itemResult = $includeItems
            ? $this->listImageItems($service, $parentQuery, $perPage, $itemPageToken)
            : ['items' => [], 'nextPageToken' => null];

        return new StorageBrowseResult(
            albums: $albumResult['albums'],
            items: $itemResult['items'],
            albumNextPageToken: $albumResult['nextPageToken'],
            itemNextPageToken: $itemResult['nextPageToken'],
        );
    }

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
}

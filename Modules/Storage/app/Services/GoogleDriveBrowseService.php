<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Modules\Storage\Models\StorageAccount;

final class GoogleDriveBrowseService
{
    public function __construct(
        private readonly StorageGoogleTokenService $tokens,
    ) {}

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function browse(
        array $credentials,
        StorageAccount $account,
        int $perPage = 25,
        ?string $albumPageToken = null,
        ?string $itemPageToken = null,
        ?string $folderId = null,
        bool $includeAlbums = true,
        bool $includeItems = true,
    ): StorageBrowseResult {
        $client = $this->tokens->clientForAccount($credentials, $account);
        $service = new Drive($client);

        $parentQuery = $folderId !== null && $folderId !== ''
            ? "'{$folderId}' in parents"
            : "'root' in parents";

        $albums = [];
        $folderNext = null;
        if ($includeAlbums) {
            $folderResponse = $service->files->listFiles([
                'q' => "{$parentQuery} and mimeType='application/vnd.google-apps.folder' and trashed=false",
                'fields' => 'nextPageToken, files(id, name, modifiedTime, thumbnailLink)',
                'pageSize' => min($perPage, 100),
                'pageToken' => $albumPageToken,
                'spaces' => 'drive',
                'orderBy' => 'name',
            ]);

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
        }

        $items = [];
        $fileNext = null;
        if ($includeItems) {
            $imageQuery = "{$parentQuery} and mimeType contains 'image/' and trashed=false";

            $fileResponse = $service->files->listFiles([
                'q' => $imageQuery,
                'fields' => 'nextPageToken, files(id, name, mimeType, thumbnailLink, size, modifiedTime, webViewLink)',
                'pageSize' => min($perPage, 100),
                'pageToken' => $itemPageToken,
                'spaces' => 'drive',
                'orderBy' => 'modifiedTime desc',
            ]);

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
        }

        return new StorageBrowseResult(
            albums: $albums,
            items: $items,
            albumNextPageToken: $folderNext !== null ? (string) $folderNext : null,
            itemNextPageToken: $fileNext !== null ? (string) $fileNext : null,
        );
    }
}

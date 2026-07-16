<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use App\Repositories\Crawler\ConnectionQueryRepository;
use Modules\Flickr\Dto\ResolvedPhotoSource;
use Modules\Flickr\Support\FlickrPhotoUrlHelper;
use RuntimeException;

final class FlickrPhotoSourceService
{
    public function __construct(
        private readonly FlickrPhotoSizeResolver $sizeResolver,
        private readonly ConnectionQueryRepository $connections,
    ) {}

    public function resolveDownload(string $sourceId, string $connectionKey): ResolvedPhotoSource
    {
        $connection = $this->connections->findByConnectionKey($connectionKey);

        if ($connection === null) {
            throw new RuntimeException("Connection not found for key: {$connectionKey}");
        }

        $candidate = $this->sizeResolver->resolve($sourceId, $connection);

        $extension = FlickrPhotoUrlHelper::resolveExtension($candidate->url);
        $originalName = FlickrPhotoUrlHelper::originalNameFor($sourceId, $extension);
        $destinationPath = "flickr/{$connectionKey}/photos/{$sourceId}_{$extension}.{$extension}";

        return new ResolvedPhotoSource(
            url: $candidate->url,
            destinationPath: $destinationPath,
            originalName: $originalName,
            variant: $candidate->variant,
            metadata: [
                'flickr_photo_id' => $sourceId,
                'connection_key' => $connectionKey,
            ],
        );
    }

    public function remoteUploadPath(string $sourceOwner, string $sourceId, string $localPath): string
    {
        $extension = FlickrPhotoUrlHelper::resolveExtension($localPath);

        return 'Flickr/'.$sourceOwner.'/Photos/'.FlickrPhotoUrlHelper::originalNameFor($sourceId, $extension);
    }
}

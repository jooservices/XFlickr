<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use App\Repositories\Crawler\ConnectionQueryRepository;
use Modules\Flickr\Support\FlickrPhotoUrlHelper;
use Modules\Storage\Contracts\SourceUrlResolver;
use Modules\Storage\Dto\ResolvedSource;
use Modules\Storage\Models\StoredFile;
use RuntimeException;

final class FlickrSourceUrlResolver implements SourceUrlResolver
{
    public function __construct(
        private readonly FlickrPhotoSizeResolver $sizeResolver,
        private readonly ConnectionQueryRepository $connections,
    ) {}

    public function resolve(string $sourceType, string $sourceId, string $connectionKey): ResolvedSource
    {
        if ($sourceType !== 'flickr_photo') {
            throw new RuntimeException("Unsupported source type: {$sourceType}");
        }

        $connection = $this->connections->findByConnectionKey($connectionKey);

        if ($connection === null) {
            throw new RuntimeException("Connection not found for key: {$connectionKey}");
        }

        $candidate = $this->sizeResolver->resolve($sourceId, $connection);

        $extension = FlickrPhotoUrlHelper::resolveExtension($candidate->url);
        $originalName = FlickrPhotoUrlHelper::originalNameFor($sourceId, $extension);
        $destinationPath = "flickr/{$connectionKey}/photos/{$sourceId}_{$extension}.{$extension}";

        return new ResolvedSource(
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

    public function remoteUploadPath(StoredFile $storedFile): string
    {
        $sourceOwner = (string) $storedFile->source_owner;
        $localPath = (string) $storedFile->local_path;
        $extension = FlickrPhotoUrlHelper::resolveExtension($localPath);
        $sourceId = (string) $storedFile->source_id;

        return 'Flickr/'.$sourceOwner.'/Photos/'.FlickrPhotoUrlHelper::originalNameFor($sourceId, $extension);
    }
}

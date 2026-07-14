<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use App\Repositories\Crawler\PhotoQueryRepository;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Flickr\Dto\DownloadCandidateDto;
use Modules\Flickr\Support\FlickrPhotoUrlHelper;
use RuntimeException;

final class FlickrPhotoSizeResolver
{
    public function __construct(
        private readonly FlickrClientFactory $clientFactory,
        private readonly PhotoQueryRepository $photos,
    ) {}

    public function resolve(string $flickrPhotoId, Connection $connection): DownloadCandidateDto
    {
        $photo = $this->photos->findByFlickrPhotoId($flickrPhotoId);
        if ($photo === null) {
            throw new RuntimeException("Photo [{$flickrPhotoId}] was not found in catalog.");
        }

        $candidate = $this->cachedCandidate($photo) ?? $this->fetchAndPersistSizes($photo, $connection);

        if ($candidate === null) {
            throw new RuntimeException(
                "No downloadable size returned by flickr.photos.getSizes for photo [{$flickrPhotoId}].",
            );
        }

        return $candidate;
    }

    private function cachedCandidate(Photo $photo): ?DownloadCandidateDto
    {
        $payload = is_array($photo->raw_payload) ? $photo->raw_payload : [];
        $sizes = $payload['sizes'] ?? null;

        if (! is_array($sizes) || $sizes === []) {
            return null;
        }

        $best = FlickrPhotoUrlHelper::bestCandidateFromGetSizesList(
            FlickrPhotoUrlHelper::normalizeGetSizesList($sizes),
        );

        if ($best === null) {
            return null;
        }

        return new DownloadCandidateDto(
            url: $best['url'],
            variant: $best['variant'],
        );
    }

    private function fetchAndPersistSizes(Photo $photo, Connection $connection): ?DownloadCandidateDto
    {
        // Connection clients are force-authenticated by FlickrClientFactory.
        $photosApi = $this->clientFactory->forConnection($connection->connection_key)->photos();
        $result = FlickrPhotoUrlHelper::fetchSizesFromApi(
            static fn (string $photoId): object => $photosApi->getSizes($photoId),
            $photo->flickr_photo_id,
        );

        if (! $result['ok']) {
            throw new RuntimeException(
                "flickr.photos.getSizes failed for photo [{$photo->flickr_photo_id}]: ".($result['message'] ?? 'unknown error'),
            );
        }

        if ($result['candidates'] === []) {
            return null;
        }

        $payload = is_array($photo->raw_payload) ? $photo->raw_payload : [];
        $this->photos->updateRawPayload($photo, [
            ...$payload,
            'sizes' => $result['raw_sizes'],
            'sizes_fetched_at' => now()->toIso8601String(),
        ]);

        $best = $result['candidates'][0];

        return new DownloadCandidateDto(
            url: $best['url'],
            variant: $best['variant'],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Flickr;

use App\Repositories\Crawler\PhotoQueryRepository;
use App\Support\FlickrPhotoUrlHelper;
use JOOservices\XFlickrCrawler\Models\Connection;
use JOOservices\XFlickrCrawler\Models\Photo;
use JOOservices\XFlickrCrawler\Services\FlickrClientFactory;
use RuntimeException;

final class FlickrPhotoSizeResolver
{
    public function __construct(
        private readonly FlickrClientFactory $clientFactory,
        private readonly PhotoQueryRepository $photos,
    ) {}

    /**
     * @return array{url: string, variant: string}
     */
    public function resolve(string $flickrPhotoId, Connection $connection): array
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

    /**
     * @return array{url: string, variant: string}|null
     */
    private function cachedCandidate(Photo $photo): ?array
    {
        $payload = is_array($photo->raw_payload) ? $photo->raw_payload : [];
        $sizes = $payload['sizes'] ?? null;

        if (! is_array($sizes) || $sizes === []) {
            return null;
        }

        return FlickrPhotoUrlHelper::bestCandidateFromGetSizesList(
            FlickrPhotoUrlHelper::normalizeGetSizesList($sizes),
        );
    }

    /**
     * @return array{url: string, variant: string}|null
     */
    private function fetchAndPersistSizes(Photo $photo, Connection $connection): ?array
    {
        $photosApi = $this->clientFactory->forConnection($connection->connection_key)->photos();
        $result = FlickrPhotoUrlHelper::fetchSizesFromApi($photosApi, $photo->flickr_photo_id);

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

        return $result['candidates'][0];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use Modules\Crawler\Models\Connection;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Support\FlickrCrawlQueryParams;
use Modules\Flickr\Exceptions\FlickrUrlResolutionException;

/**
 * Resolve Flickr profile / photo page URLs to a contact row for catalog persist.
 */
final class FlickrUrlResolverService
{
    public function __construct(
        private readonly FlickrClientFactory $clients,
    ) {}

    /**
     * @return array{nsid: string, username: string|null, realname: string|null, friend: int, family: int}
     */
    public function resolveContactRow(Connection $connection, string $url): array
    {
        $normalized = $this->normalizeUrl($url);
        $nsid = $this->resolveNsid($connection, $normalized);
        $profile = $this->fetchPerson($connection, $nsid);

        return [
            'nsid' => $nsid,
            'username' => $profile['username'],
            'realname' => $profile['realname'],
            'friend' => 0,
            'family' => 0,
        ];
    }

    public function normalizeUrl(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            throw new FlickrUrlResolutionException('Paste a Flickr people or photo URL.');
        }

        if (! preg_match('#^https?://#i', $trimmed)) {
            $trimmed = 'https://'.$trimmed;
        }

        $parts = parse_url($trimmed);
        if (! is_array($parts) || ! isset($parts['host'])) {
            throw new FlickrUrlResolutionException('That does not look like a valid URL.');
        }

        $host = strtolower((string) $parts['host']);
        if (! str_ends_with($host, 'flickr.com') && $host !== 'flic.kr') {
            throw new FlickrUrlResolutionException('URL must be a flickr.com (or flic.kr) link.');
        }

        return $trimmed;
    }

    public function extractPhotoId(string $url): ?string
    {
        if (preg_match('#/photos/[^/]+/(\d+)#', $url, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function resolveNsid(Connection $connection, string $url): string
    {
        $photoId = $this->extractPhotoId($url);
        if ($photoId !== null) {
            $info = $this->call($connection, 'flickr.photos.getInfo', ['photo_id' => $photoId]);
            $owner = $info['photo']['owner']['nsid'] ?? $info['photo']['owner'] ?? null;
            if (is_array($owner)) {
                $owner = $owner['nsid'] ?? null;
            }
            if (is_string($owner) && $owner !== '') {
                return $owner;
            }

            throw new FlickrUrlResolutionException('Could not resolve the photo owner from that URL.');
        }

        $lookup = $this->call($connection, 'flickr.urls.lookupUser', ['url' => $url]);
        $nsid = $lookup['user']['id'] ?? null;
        if (is_string($nsid) && $nsid !== '') {
            return $nsid;
        }

        throw new FlickrUrlResolutionException('Could not resolve a Flickr user from that URL.');
    }

    /**
     * @return array{username: string|null, realname: string|null}
     */
    private function fetchPerson(Connection $connection, string $nsid): array
    {
        $info = $this->call($connection, 'flickr.people.getInfo', ['user_id' => $nsid]);
        $person = is_array($info['person'] ?? null) ? $info['person'] : [];

        return [
            'username' => $this->contentString($person['username'] ?? null),
            'realname' => $this->contentString($person['realname'] ?? null),
        ];
    }

    /**
     * @param  array<string, string>  $params
     * @return array<string, mixed>
     */
    private function call(Connection $connection, string $method, array $params): array
    {
        $client = $this->clients->forConnection($connection->connection_key);
        $response = FlickrCrawlQueryParams::call($client, $method, $params);

        if (! $response->ok) {
            $message = $response->error !== null
                ? (string) $response->error->message
                : 'Flickr API request failed.';
            throw new FlickrUrlResolutionException($message);
        }

        return $response->data;
    }

    private function contentString(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_array($value) && isset($value['_content']) && is_string($value['_content'])) {
            $content = $value['_content'];

            return $content !== '' ? $content : null;
        }

        return null;
    }
}

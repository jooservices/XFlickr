<?php

declare(strict_types=1);

namespace Modules\Crawler\Support;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\Flickr\DTO\Common\RequestOptionsData;
use JOOservices\Flickr\Flickr;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;

/**
 * Builds Flickr REST parameters for crawl jobs.
 *
 * Visibility filters (safe_search, privacy_filter) are omitted unless explicitly
 * configured to 1–5. Zero means "no filter" — use Flickr defaults.
 */
final class FlickrCrawlQueryParams
{
    public const PHOTO_EXTRAS = 'owner_name,path_alias,url_sq,url_t,url_s,url_m,url_o';

    /**
     * @return array<string, mixed>
     */
    public static function peoplePhotos(string $userId, int $page, int $perPage): array
    {
        return array_merge(
            [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
                'extras' => self::PHOTO_EXTRAS,
            ],
            self::visibilityFilters(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function photosetsList(string $userId, int $page, int $perPage): array
    {
        return array_merge(
            [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
            ],
            self::visibilityFilters(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function photosetPhotos(string $photosetId, int $page, int $perPage): array
    {
        return array_merge(
            [
                'photoset_id' => $photosetId,
                'page' => $page,
                'per_page' => $perPage,
                'extras' => self::PHOTO_EXTRAS,
            ],
            self::visibilityFilters(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function galleriesList(string $userId, int $page, int $perPage): array
    {
        return array_merge(
            [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
            ],
            self::visibilityFilters(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function galleryPhotos(string $galleryId, int $page, int $perPage): array
    {
        return array_merge(
            [
                'gallery_id' => $galleryId,
                'page' => $page,
                'per_page' => $perPage,
                'extras' => self::PHOTO_EXTRAS,
            ],
            self::visibilityFilters(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function favoritesList(string $userId, int $page, int $perPage): array
    {
        return array_merge(
            [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
            ],
            self::visibilityFilters(),
        );
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function call(Flickr $client, string $method, array $parameters, bool $authenticated = true): ApiResponseData
    {
        if ($authenticated) {
            return $client->raw()->call($method, $parameters, new RequestOptionsData(authenticated: true));
        }

        return $client->raw()->call($method, $parameters);
    }

    /**
     * @return array<string, int>
     */
    public static function visibilityFilters(): array
    {
        $filters = [];

        $safeSearch = self::resolveFilterInt('safe_search', 'people_photos_safe_search');
        if ($safeSearch > 0) {
            $filters['safe_search'] = $safeSearch;
        }

        $privacyFilter = self::resolveFilterInt('privacy_filter');
        if ($privacyFilter > 0) {
            $filters['privacy_filter'] = $privacyFilter;
        }

        return $filters;
    }

    private static function resolveFilterInt(string $key, ?string $legacyKey = null): int
    {
        if (RuntimeConfig::has("xflickr_crawl.{$key}")) {
            return XFlickrConfig::crawlInt($key, 0);
        }

        if ($legacyKey !== null && RuntimeConfig::has("xflickr_crawl.{$legacyKey}")) {
            return XFlickrConfig::crawlInt($legacyKey, 0);
        }

        $configured = config("xflickr-crawler.crawl.{$key}");
        if (is_numeric($configured) && (int) $configured > 0) {
            return (int) $configured;
        }

        if ($legacyKey !== null) {
            $legacyConfigured = config("xflickr-crawler.crawl.{$legacyKey}");
            if (is_numeric($legacyConfigured) && (int) $legacyConfigured > 0) {
                return (int) $legacyConfigured;
            }
        }

        return 0;
    }
}

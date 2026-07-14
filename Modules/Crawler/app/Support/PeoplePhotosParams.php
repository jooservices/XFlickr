<?php

declare(strict_types=1);

namespace Modules\Crawler\Support;

/** @deprecated Use FlickrCrawlQueryParams::peoplePhotos() */
final class PeoplePhotosParams
{
    /**
     * @return array<string, mixed>
     */
    public static function query(string $userId, int $page, int $perPage): array
    {
        return FlickrCrawlQueryParams::peoplePhotos($userId, $page, $perPage);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Flickr;

use JOOservices\XFlickrCrawler\Enums\CrawlType;
use JOOservices\XFlickrCrawler\Facades\FlickrService;
use JOOservices\XFlickrCrawler\FlickrConnection;
use JOOservices\XFlickrCrawler\Models\Connection;
use JOOservices\XFlickrCrawler\Models\CrawlRun;
use RuntimeException;

final class FlickrCrawlService
{
    public function connection(Connection $connection): FlickrConnection
    {
        $payload = $connection->token_payload;
        if ($payload === '') {
            throw new RuntimeException("Flickr connection [{$connection->connection_key}] has no token payload.");
        }

        return FlickrService::connection(
            $connection->connection_key,
            $payload,
            $connection->app_profile ?: 'main',
        );
    }

    public function crawl(Connection $connection, CrawlType $type, ?string $subjectNsid = null): CrawlRun
    {
        $flickrConnection = $this->connection($connection);
        $subject = $subjectNsid ?? $connection->connection_key;

        return match ($type) {
            CrawlType::Contacts => $flickrConnection->contacts(),
            CrawlType::Photos => $flickrConnection->photos($subject),
            CrawlType::Photosets => $flickrConnection->photosets($subject),
            CrawlType::Galleries => $flickrConnection->galleries($subject),
            CrawlType::Favorites => $flickrConnection->favorites($subject),
        };
    }

    /**
     * @param  list<CrawlType>  $types
     * @return list<CrawlRun>
     */
    public function crawlMany(Connection $connection, array $types, ?string $subjectNsid = null): array
    {
        $runs = [];
        foreach ($types as $type) {
            $runs[] = $this->crawl($connection, $type, $subjectNsid);
        }

        return $runs;
    }
}

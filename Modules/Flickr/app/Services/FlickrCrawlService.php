<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use JOOservices\XFlickrCrawler\Enums\CrawlType;
use JOOservices\XFlickrCrawler\Facades\FlickrService;
use JOOservices\XFlickrCrawler\FlickrConnection;
use JOOservices\XFlickrCrawler\Models\Connection;
use JOOservices\XFlickrCrawler\Models\CrawlRun;
use Modules\Flickr\Exceptions\FlickrTokenInvalidException;
use RuntimeException;

final class FlickrCrawlService
{
    public function __construct(
        private readonly FlickrTokenHealthService $tokenHealth,
    ) {}

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

    public function crawl(
        Connection $connection,
        CrawlType $type,
        ?string $subjectNsid = null,
        ?int $spiderRunId = null,
        ?int $spiderFrontierItemId = null,
    ): CrawlRun {
        $this->assertTokenHealthy($connection);

        return $this->crawlWithoutHealthCheck(
            $connection,
            $type,
            $subjectNsid,
            $spiderRunId,
            $spiderFrontierItemId,
        );
    }

    /**
     * @param  list<CrawlType>  $types
     * @return list<CrawlRun>
     */
    public function crawlMany(
        Connection $connection,
        array $types,
        ?string $subjectNsid = null,
        ?int $spiderRunId = null,
        ?int $spiderFrontierItemId = null,
    ): array {
        $this->assertTokenHealthy($connection);

        $runs = [];
        foreach ($types as $type) {
            $runs[] = $this->crawlWithoutHealthCheck(
                $connection,
                $type,
                $subjectNsid,
                $spiderRunId,
                $spiderFrontierItemId,
            );
        }

        return $runs;
    }

    private function crawlWithoutHealthCheck(
        Connection $connection,
        CrawlType $type,
        ?string $subjectNsid = null,
        ?int $spiderRunId = null,
        ?int $spiderFrontierItemId = null,
    ): CrawlRun {
        $flickrConnection = $this->connection($connection);
        $subject = $subjectNsid ?? $connection->connection_key;

        return match ($type) {
            CrawlType::Contacts => $flickrConnection->contacts(
                $this->contactsSubject($connection, $subjectNsid),
                $spiderRunId,
                $spiderFrontierItemId,
            ),
            CrawlType::Photos => $flickrConnection->photos($subject),
            CrawlType::Photosets => $flickrConnection->photosets($subject),
            CrawlType::Galleries => $flickrConnection->galleries($subject),
            CrawlType::Favorites => $flickrConnection->favorites($subject),
        };
    }

    private function assertTokenHealthy(Connection $connection): void
    {
        if (! $this->tokenHealth->probe($connection, useCache: true)->valid) {
            throw FlickrTokenInvalidException::forConnection($connection);
        }
    }

    private function contactsSubject(Connection $connection, ?string $subjectNsid): ?string
    {
        if ($subjectNsid === null || $subjectNsid === '' || $subjectNsid === $connection->connection_key) {
            return null;
        }

        return $subjectNsid;
    }
}

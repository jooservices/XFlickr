<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use App\Support\ThirdPartyApiLogger;
use Illuminate\Support\Facades\Log;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Facades\FlickrService;
use Modules\Crawler\FlickrConnection;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Support\XFlickrConfig;
use Modules\Flickr\Exceptions\FlickrTokenInvalidException;
use Modules\Flickr\Exceptions\GlobalCrawlPauseException;
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
        $this->assertCrawlAllowed($connection);

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
        $this->assertCrawlAllowed($connection);

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

    private function assertCrawlAllowed(Connection $connection): void
    {
        $connectionKeyFp = ThirdPartyApiLogger::fingerprint($connection->connection_key);

        if (XFlickrConfig::globalPause()) {
            Log::warning('Crawl blocked by global pause.', [
                'connection_key_fp' => $connectionKeyFp,
            ]);

            throw GlobalCrawlPauseException::active();
        }

        if (! $this->tokenHealth->probe($connection, useCache: true)->valid) {
            Log::warning('Crawl blocked by invalid Flickr token.', [
                'connection_key_fp' => $connectionKeyFp,
            ]);

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

<?php

declare(strict_types=1);

namespace Modules\Settings\Services;

use App\Repositories\Crawler\ContactQueryRepository;
use App\Repositories\Crawler\CrawlRunQueryRepository;
use Modules\Flickr\Services\FlickrAccountsService;

final class OnboardingStatusService
{
    public function __construct(
        private readonly FlickrAccountsService $flickr,
        private readonly CrawlRunQueryRepository $crawlRuns,
        private readonly ContactQueryRepository $contacts,
    ) {}

    public function hasCompletedCrawl(): bool
    {
        $connectionKeys = $this->flickr->listConnections()
            ->map(fn ($connection): string => (string) $connection->connection_key)
            ->values()
            ->all();

        if ($connectionKeys === []) {
            return false;
        }

        if ($this->crawlRuns->countByConnectionsAndStatus($connectionKeys, 'completed') > 0) {
            return true;
        }

        foreach ($this->contacts->countsGroupedByConnection($connectionKeys) as $count) {
            if ((int) $count > 0) {
                return true;
            }
        }

        return false;
    }
}

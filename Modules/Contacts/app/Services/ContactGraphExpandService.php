<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Repositories\CrawlRunRepository;
use Modules\Flickr\Services\FlickrAccountsService;

final class ContactGraphExpandService
{
    public function __construct(
        private readonly FlickrAccountsService $crawls,
        private readonly CrawlRunRepository $crawlRuns,
    ) {}

    /**
     * @return array{
     *     crawl_run_id: int,
     *     status: string,
     *     subject_nsid: string,
     *     reexpand: bool
     * }
     */
    public function expand(Connection $connection, string $subjectNsid): array
    {
        $storedSubject = $subjectNsid === $connection->connection_key ? null : $subjectNsid;
        $existing = $this->crawlRuns->findRunningContactsCrawl($connection->connection_key, $storedSubject);

        if ($existing instanceof CrawlRun) {
            return [
                'crawl_run_id' => (int) $existing->id,
                'status' => $this->statusValue($existing),
                'subject_nsid' => $subjectNsid,
                'reexpand' => false,
            ];
        }

        $crawlSubject = $this->resolveCrawlSubject($connection, $subjectNsid);
        $run = $this->crawls->crawl($connection, CrawlType::Contacts, $crawlSubject);

        return [
            'crawl_run_id' => (int) $run->id,
            'status' => $this->statusValue($run),
            'subject_nsid' => $subjectNsid,
            'reexpand' => true,
        ];
    }

    private function resolveCrawlSubject(Connection $connection, string $subjectNsid): ?string
    {
        if ($subjectNsid === $connection->connection_key) {
            return null;
        }

        return $subjectNsid;
    }

    private function statusValue(CrawlRun $run): string
    {
        return $run->status instanceof CrawlRunStatus ? $run->status->value : (string) $run->getAttribute('status');
    }
}

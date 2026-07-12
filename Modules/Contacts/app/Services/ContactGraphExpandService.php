<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use JOOservices\XFlickrCrawler\Enums\CrawlRunStatus;
use JOOservices\XFlickrCrawler\Enums\CrawlType;
use JOOservices\XFlickrCrawler\Models\Connection;
use JOOservices\XFlickrCrawler\Models\CrawlRun;
use Modules\Flickr\Services\FlickrCrawlService;

final class ContactGraphExpandService
{
    public function __construct(
        private readonly FlickrCrawlService $crawls,
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
        $existing = $this->findRunningContactsCrawl($connection->connection_key, $subjectNsid);

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

    private function findRunningContactsCrawl(string $connectionKey, string $subjectNsid): ?CrawlRun
    {
        $storedSubject = $subjectNsid === $connectionKey ? null : $subjectNsid;

        $query = CrawlRun::query()
            ->where('connection_key', $connectionKey)
            ->where('crawl_type', CrawlType::Contacts->value)
            ->where('status', CrawlRunStatus::Running->value)
            ->orderByDesc('id');

        if ($storedSubject === null) {
            $query->where(function ($builder) use ($connectionKey): void {
                $builder
                    ->whereNull('subject_nsid')
                    ->orWhere('subject_nsid', $connectionKey);
            });
        } else {
            $query->where('subject_nsid', $storedSubject);
        }

        $run = $query->first();

        return $run instanceof CrawlRun ? $run : null;
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

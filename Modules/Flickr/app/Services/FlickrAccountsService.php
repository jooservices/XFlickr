<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use Illuminate\Support\Collection;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Models\CrawlRun;
use Modules\Flickr\Dto\DownloadCandidateDto;
use Modules\Flickr\Dto\FlickrTokenHealthResult;
use Modules\Flickr\Services\RateLimit\Presenter;

final class FlickrAccountsService
{
    public function __construct(
        private readonly FlickrOAuthService $oauth,
        private readonly FlickrCrawlService $crawl,
        private readonly FlickrTokenHealthService $tokenHealth,
        private readonly Presenter $rateLimit,
        private readonly CrawlStatusQueryService $crawlStatus,
        private readonly FlickrAppProfileService $appProfiles,
        private readonly FlickrPhotoSizeResolver $photoSizes,
        private readonly FlickrUrlResolverService $urlResolver,
    ) {}

    /**
     * @return array{connected: bool, account: array<string, mixed>|null}
     */
    public function status(): array
    {
        return $this->oauth->status();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listAccounts(): Collection
    {
        return $this->oauth->listAccounts();
    }

    /**
     * @return Collection<int, Connection>
     */
    public function listConnections(): Collection
    {
        return $this->oauth->listConnections();
    }

    public function activeConnection(): ?Connection
    {
        return $this->oauth->activeConnection();
    }

    public function crawl(
        Connection $connection,
        CrawlType $type,
        ?string $subjectNsid = null,
        ?int $spiderRunId = null,
        ?int $spiderFrontierItemId = null,
    ): CrawlRun {
        return $this->crawl->crawl(
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
        return $this->crawl->crawlMany(
            $connection,
            $types,
            $subjectNsid,
            $spiderRunId,
            $spiderFrontierItemId,
        );
    }

    public function probeTokenHealth(Connection $connection, bool $useCache = true): FlickrTokenHealthResult
    {
        return $this->tokenHealth->probe($connection, $useCache);
    }

    /**
     * @return array<string, mixed>
     */
    public function rateLimitPresent(string $connectionKey): array
    {
        return $this->rateLimit->present($connectionKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function crawlStatusSummary(Connection $connection): array
    {
        return $this->crawlStatus->summary($connection);
    }

    /**
     * @return array{data: list<mixed>, meta: array<string, mixed>}
     */
    public function crawlStatusRuns(Connection $connection, string $sort, string $direction, int $perPage, int $page): array
    {
        return $this->crawlStatus->runs($connection, $sort, $direction, $perPage, $page);
    }

    /**
     * @return array{data: list<mixed>, meta: array<string, mixed>}
     */
    public function crawlStatusLogs(Connection $connection, int $perPage, int $page): array
    {
        return $this->crawlStatus->logs($connection, $perPage, $page);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAppProfiles(): array
    {
        return $this->appProfiles->listPublic()->values()->all();
    }

    /**
     * @param  array{profile?: string, label?: string|null, api_key: string, api_secret: string, callback_url?: string|null}  $data
     */
    public function saveAppProfile(array $data): string
    {
        return $this->appProfiles->save($data);
    }

    public function deleteAppProfile(string $profile): string
    {
        return $this->appProfiles->delete($profile);
    }

    public function defaultCallbackUrl(): string
    {
        return $this->appProfiles->defaultCallbackUrl();
    }

    public function resolvePhotoSize(string $flickrPhotoId, Connection $connection): DownloadCandidateDto
    {
        return $this->photoSizes->resolve($flickrPhotoId, $connection);
    }

    /**
     * @return array{nsid: string, username: string|null, realname: string|null, friend: int, family: int}
     */
    public function resolveContactFromUrl(Connection $connection, string $url): array
    {
        return $this->urlResolver->resolveContactRow($connection, $url);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Crawler;

use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Services\CrawlingService;

final class FlickrConnection
{
    public function __construct(
        private readonly string $connectionKey,
        private readonly string $tokenPayload,
        private readonly ?string $appProfile,
        private readonly CrawlingService $crawling,
    ) {}

    public function contacts(
        ?string $subjectNsid = null,
        ?int $spiderRunId = null,
        ?int $spiderFrontierItemId = null,
    ): CrawlRun {
        if ($subjectNsid === null || $subjectNsid === '') {
            return $this->crawling->startContacts(
                $this->connectionKey,
                $this->tokenPayload,
                $this->appProfile,
                $spiderRunId,
                $spiderFrontierItemId,
            );
        }

        return $this->crawling->startContactsForSubject(
            $this->connectionKey,
            $this->tokenPayload,
            $subjectNsid,
            $this->appProfile,
            $spiderRunId,
            $spiderFrontierItemId,
        );
    }

    public function photos(string $nsid): CrawlRun
    {
        return $this->crawling->startPhotos($this->connectionKey, $this->tokenPayload, $nsid, $this->appProfile);
    }

    public function photosets(string $nsid): CrawlRun
    {
        return $this->crawling->startPhotosets($this->connectionKey, $this->tokenPayload, $nsid, $this->appProfile);
    }

    public function galleries(string $nsid): CrawlRun
    {
        return $this->crawling->startGalleries($this->connectionKey, $this->tokenPayload, $nsid, $this->appProfile);
    }

    public function favorites(string $nsid): CrawlRun
    {
        return $this->crawling->startFavorites($this->connectionKey, $this->tokenPayload, $nsid, $this->appProfile);
    }

    public function connectionKey(): string
    {
        return $this->connectionKey;
    }
}

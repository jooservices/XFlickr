<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Repositories\ConnectionRepository;
use Modules\Crawler\Support\XFlickrConfig;
use RuntimeException;

final class CrawlingService
{
    public function __construct(
        private readonly FlickrSpiderService $spider,
        private readonly ConnectionRepository $connections,
    ) {}

    public function startContacts(
        string $connectionKey,
        string $tokenPayload,
        ?string $appProfile = null,
        ?int $spiderRunId = null,
        ?int $spiderFrontierItemId = null,
    ): CrawlRun {
        $this->ensureConnection($connectionKey, $tokenPayload, $appProfile);
        $this->guardGlobalPause();

        $run = $this->spider->createRun(
            $connectionKey,
            CrawlType::Contacts,
            null,
            $spiderRunId,
            $spiderFrontierItemId,
        );
        $this->spider->enqueueTarget($run, TaskType::ContactsPage, null, null, 1);
        $this->spider->dispatchDueTargets();

        return $run;
    }

    public function startContactsForSubject(
        string $connectionKey,
        string $tokenPayload,
        string $subjectNsid,
        ?string $appProfile = null,
        ?int $spiderRunId = null,
        ?int $spiderFrontierItemId = null,
    ): CrawlRun {
        $this->ensureConnection($connectionKey, $tokenPayload, $appProfile);
        $this->guardGlobalPause();

        $run = $this->spider->createRun(
            $connectionKey,
            CrawlType::Contacts,
            $subjectNsid,
            $spiderRunId,
            $spiderFrontierItemId,
        );
        $this->spider->enqueueTarget($run, TaskType::SubjectContactsPage, $subjectNsid, null, 1);
        $this->spider->dispatchDueTargets();

        return $run;
    }

    public function startPhotos(string $connectionKey, string $tokenPayload, string $nsid, ?string $appProfile = null): CrawlRun
    {
        $this->ensureConnection($connectionKey, $tokenPayload, $appProfile);
        $this->guardGlobalPause();

        $run = $this->spider->createRun($connectionKey, CrawlType::Photos, $nsid);
        $this->spider->enqueueTarget($run, TaskType::PeoplePhotos, $nsid, null, 1);
        $this->spider->dispatchDueTargets();

        return $run;
    }

    public function startPhotosets(string $connectionKey, string $tokenPayload, string $nsid, ?string $appProfile = null): CrawlRun
    {
        $this->ensureConnection($connectionKey, $tokenPayload, $appProfile);
        $this->guardGlobalPause();

        $run = $this->spider->createRun($connectionKey, CrawlType::Photosets, $nsid);
        $this->spider->enqueueTarget($run, TaskType::PhotosetsList, $nsid, null, 1);
        $this->spider->dispatchDueTargets();

        return $run;
    }

    public function startGalleries(string $connectionKey, string $tokenPayload, string $nsid, ?string $appProfile = null): CrawlRun
    {
        $this->ensureConnection($connectionKey, $tokenPayload, $appProfile);
        $this->guardGlobalPause();

        $run = $this->spider->createRun($connectionKey, CrawlType::Galleries, $nsid);
        $this->spider->enqueueTarget($run, TaskType::GalleriesList, $nsid, null, 1);
        $this->spider->dispatchDueTargets();

        return $run;
    }

    public function startFavorites(string $connectionKey, string $tokenPayload, string $nsid, ?string $appProfile = null): CrawlRun
    {
        $this->ensureConnection($connectionKey, $tokenPayload, $appProfile);
        $this->guardGlobalPause();

        $run = $this->spider->createRun($connectionKey, CrawlType::Favorites, $nsid);
        $this->spider->enqueueTarget($run, TaskType::FavoritesPage, $nsid, null, 1);
        $this->spider->dispatchDueTargets();

        return $run;
    }

    private function ensureConnection(string $connectionKey, string $tokenPayload, ?string $appProfile = null): Connection
    {
        $profile = $appProfile !== null
            ? XFlickrConfig::sanitizeProfileSlug($appProfile)
            : XFlickrConfig::defaultAppProfile();

        $existing = $this->connections->findByKey($connectionKey);

        if ($existing !== null) {
            if ($existing->app_profile !== $profile) {
                return $this->connections->update($existing, ['app_profile' => $profile]);
            }

            return $existing;
        }

        return $this->connections->updateOrCreateByKey($connectionKey, [
            'app_profile' => $profile,
            'token_payload' => $tokenPayload,
        ]);
    }

    private function guardGlobalPause(): void
    {
        if (XFlickrConfig::globalPause()) {
            throw new RuntimeException('Global crawl pause is active.');
        }
    }
}

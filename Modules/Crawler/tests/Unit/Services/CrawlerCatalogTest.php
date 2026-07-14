<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Services;

use Illuminate\Support\Facades\Event;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Events\CrawlRunCompleted;
use Modules\Crawler\Facades\FlickrService;
use Modules\Crawler\Models\ConnectionContact;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Models\Favorite;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Modules\Crawler\Services\ConnectionRegistryService;
use Modules\Crawler\Services\CrawlerCatalog;
use Modules\Crawler\Services\CrawlerRuns;
use Modules\Crawler\Services\FlickrCatalogService;
use Modules\Crawler\Services\FlickrSpiderService;
use Modules\Crawler\Tests\TestCase;

final class CrawlerCatalogTest extends TestCase
{
    public function test_counts_for_subject_includes_favorites_scoped_by_connection(): void
    {
        Photo::query()->create([
            'flickr_photo_id' => 'p-1',
            'owner_nsid' => 'sub@N01',
            'title' => 'T',
        ]);
        Photoset::query()->create([
            'flickr_photoset_id' => 'ps-1',
            'owner_nsid' => 'sub@N01',
            'title' => 'S',
        ]);
        Gallery::query()->create([
            'flickr_gallery_id' => 'g-1',
            'owner_nsid' => 'sub@N01',
            'title' => 'G',
        ]);

        $photo = Photo::query()->where('flickr_photo_id', 'p-1')->firstOrFail();
        Favorite::query()->create([
            'connection_key' => 'conn-a',
            'subject_nsid' => 'sub@N01',
            'xflickr_photo_id' => $photo->id,
            'photo_owner_nsid' => 'other@N01',
            'discovered_at' => now(),
        ]);
        Favorite::query()->create([
            'connection_key' => 'conn-b',
            'subject_nsid' => 'sub@N01',
            'xflickr_photo_id' => $photo->id,
            'photo_owner_nsid' => 'other@N01',
            'discovered_at' => now(),
        ]);

        $counts = app(CrawlerCatalog::class)->countsForSubject('conn-a', 'sub@N01');

        $this->assertSame(1, $counts->photos);
        $this->assertSame(1, $counts->photosets);
        $this->assertSame(1, $counts->galleries);
        $this->assertSame(1, $counts->favorites);
    }

    public function test_contacts_for_connection_returns_scoped_rows(): void
    {
        app(FlickrCatalogService::class)->persistContacts([
            ['nsid' => 'c1@N01', 'username' => 'c1'],
            ['nsid' => 'c2@N01', 'username' => 'c2'],
        ], 'conn-1');

        app(FlickrCatalogService::class)->persistContacts([
            ['nsid' => 'c3@N01', 'username' => 'c3'],
        ], 'conn-2');

        $contacts = app(CrawlerCatalog::class)->contactsForConnection('conn-1');

        $this->assertCount(2, $contacts);
        $this->assertSame(['c1@N01', 'c2@N01'], $contacts->pluck('contact_nsid')->sort()->values()->all());
    }

    public function test_contact_profiles_for_connection_returns_profiles(): void
    {
        app(FlickrCatalogService::class)->persistContacts([
            ['nsid' => 'alice@N01', 'username' => 'alice', 'realname' => 'Alice'],
            ['nsid' => 'bob@N01', 'username' => 'bob', 'realname' => 'Bob'],
        ], 'conn-profiles');

        $profiles = app(CrawlerCatalog::class)->contactProfilesForConnection('conn-profiles');

        $this->assertCount(2, $profiles);
        $this->assertSame(['alice@N01', 'bob@N01'], $profiles->pluck('nsid')->sort()->values()->all());
    }

    public function test_contact_profiles_for_connection_returns_empty_when_no_contacts(): void
    {
        $profiles = app(CrawlerCatalog::class)->contactProfilesForConnection('empty-conn');

        $this->assertCount(0, $profiles);
    }

    public function test_contact_profiles_for_connection_paginated_supports_search(): void
    {
        app(FlickrCatalogService::class)->persistContacts([
            ['nsid' => 'alice@N01', 'username' => 'alice', 'realname' => 'Alice'],
            ['nsid' => 'bob@N01', 'username' => 'bob', 'realname' => 'Bob'],
        ], 'conn-page');

        ConnectionContact::query()->create([
            'connection_key' => 'conn-page',
            'contact_nsid' => 'fav-owner@N01',
            'discovered_at' => now(),
        ]);
        app(FlickrCatalogService::class)->persistContacts([
            ['nsid' => 'fav-owner@N01', 'username' => 'favowner', 'realname' => 'Favorite Owner'],
        ]);

        $paginator = app(CrawlerCatalog::class)->contactProfilesForConnectionPaginated(
            connectionKey: 'conn-page',
            search: 'fav',
            page: 1,
            perPage: 10,
        );

        $this->assertSame(1, $paginator->total());
        $this->assertSame('fav-owner@N01', $paginator->items()[0]->nsid);
    }

    public function test_active_runs_for_connection(): void
    {
        CrawlRun::query()->create([
            'connection_key' => 'conn-x',
            'crawl_type' => CrawlType::Photos->value,
            'subject_nsid' => 'sub@N01',
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);
        CrawlRun::query()->create([
            'connection_key' => 'conn-x',
            'crawl_type' => CrawlType::Contacts->value,
            'status' => CrawlRunStatus::Completed,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $active = app(CrawlerRuns::class)->activeForConnection('conn-x');

        $this->assertCount(1, $active);
        $this->assertSame(CrawlType::Photos->value, $active->first()->crawl_type);
    }

    public function test_manager_exposes_catalog_and_runs(): void
    {
        $manager = FlickrService::getFacadeRoot();

        $this->assertInstanceOf(CrawlerCatalog::class, $manager->catalog());
        $this->assertInstanceOf(CrawlerRuns::class, $manager->runs());
        $this->assertInstanceOf(ConnectionRegistryService::class, $manager->connections());
    }

    public function test_crawl_run_completed_event_fires_when_run_finishes(): void
    {
        Event::fake([CrawlRunCompleted::class]);

        $run = CrawlRun::query()->create([
            'connection_key' => 'evt-conn',
            'crawl_type' => CrawlType::Contacts->value,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::ContactsPage,
            'page' => 1,
            'status' => CrawlStatus::Completed,
        ]);

        app(FlickrSpiderService::class)->maybeCompleteRun($run->fresh());

        Event::assertDispatched(CrawlRunCompleted::class, function (CrawlRunCompleted $event) use ($run): bool {
            return $event->run->id === $run->id
                && $event->run->status === CrawlRunStatus::Completed;
        });
    }
}

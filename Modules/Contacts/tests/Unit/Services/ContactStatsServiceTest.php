<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\ContactFactory;
use Database\Factories\Crawler\PhotoFactory;
use Modules\Contacts\Services\ContactStatsService;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Models\Favorite;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Models\TransferBatch;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactStatsServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_for_contacts_returns_empty_array_for_empty_input(): void
    {
        $connection = $this->createFlickrConnection();

        $counts = app(ContactStatsService::class)->catalogCountsFor($connection, []);

        $this->assertSame([], $counts);
    }

    public function test_for_contacts_returns_zero_counts_for_contacts_without_catalog_rows(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();

        $counts = app(ContactStatsService::class)->catalogCountsFor($connection, [$contactNsid]);

        $this->assertSame([
            'photos' => 0,
            'photosets' => 0,
            'galleries' => 0,
            'favorites' => 0,
        ], $counts[$contactNsid]);
    }

    public function test_for_contacts_aggregates_owner_and_connection_scoped_counts(): void
    {
        $connection = $this->createFlickrConnection();
        $otherConnection = $this->createFlickrConnection();
        $withCatalog = FlickrNsid::fake();
        $withoutCatalog = FlickrNsid::fake();

        Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $withCatalog,
        ]));
        Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $withCatalog,
        ]));

        Photoset::query()->create([
            'flickr_photoset_id' => fake()->uuid(),
            'owner_nsid' => $withCatalog,
            'title' => fake()->words(2, true),
            'photo_count' => 1,
        ]);

        Gallery::query()->create([
            'flickr_gallery_id' => fake()->uuid(),
            'owner_nsid' => $withCatalog,
            'title' => fake()->words(2, true),
            'photo_count' => 1,
        ]);

        $favoritePhoto = Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $withCatalog,
        ]));
        Favorite::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $withCatalog,
            'xflickr_photo_id' => $favoritePhoto->id,
            'photo_owner_nsid' => $withCatalog,
            'discovered_at' => now(),
        ]);
        Favorite::query()->create([
            'connection_key' => $otherConnection->connection_key,
            'subject_nsid' => $withCatalog,
            'xflickr_photo_id' => $favoritePhoto->id,
            'photo_owner_nsid' => $withCatalog,
            'discovered_at' => now(),
        ]);

        $counts = app(ContactStatsService::class)->catalogCountsFor(
            $connection,
            [$withCatalog, $withoutCatalog],
        );

        $this->assertSame([
            'photos' => 3,
            'photosets' => 1,
            'galleries' => 1,
            'favorites' => 1,
        ], $counts[$withCatalog]);
        $this->assertSame([
            'photos' => 0,
            'photosets' => 0,
            'galleries' => 0,
            'favorites' => 0,
        ], $counts[$withoutCatalog]);
    }

    public function test_detail_stats_for_returns_db_counts_and_api_totals(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();
        $contact = ContactFactory::new()->create(['nsid' => $contactNsid]);
        ConnectionContactFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
        ]);

        Photo::query()->create(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $contactNsid,
            'raw_payload' => ['sizes' => ['small' => ['url' => 'https://example.com/s.jpg']]],
        ]));

        CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'photos',
            'status' => CrawlRunStatus::Completed->value,
            'photos_discovered' => 12,
        ]);

        $photosetsRun = CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'photosets',
            'status' => CrawlRunStatus::Completed->value,
        ]);
        CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $photosetsRun->id,
            'task_type' => TaskType::PhotosetsList,
            'subject_nsid' => $contactNsid,
            'status' => CrawlStatus::Completed,
            'last_result_count' => 4,
        ]);

        $galleriesRun = CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'galleries',
            'status' => CrawlRunStatus::Completed->value,
        ]);
        CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $galleriesRun->id,
            'task_type' => TaskType::GalleriesList,
            'subject_nsid' => $contactNsid,
            'status' => CrawlStatus::Completed,
            'last_result_count' => 2,
        ]);

        $favoritesRun = CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'favorites',
            'status' => CrawlRunStatus::Completed->value,
        ]);
        CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $favoritesRun->id,
            'task_type' => TaskType::FavoritesPage,
            'subject_nsid' => $contactNsid,
            'status' => CrawlStatus::Completed,
            'last_result_count' => 7,
        ]);

        $stats = app(ContactStatsService::class)->detailStatsFor($connection, $contactNsid);

        $this->assertSame(1, $stats['photos']['db']);
        $this->assertSame(1, $stats['photos']['with_sizes']);
        $this->assertSame(12, $stats['photos']['in_api']);
        $this->assertSame(4, $stats['photosets']['in_api']);
        $this->assertSame(2, $stats['galleries']['in_api']);
        $this->assertSame(7, $stats['favorites']['in_api']);
    }

    public function test_detail_stats_for_returns_zero_counts_when_contact_is_unknown(): void
    {
        $connection = $this->createFlickrConnection();
        $unknownNsid = FlickrNsid::fake();

        $stats = app(ContactStatsService::class)->detailStatsFor($connection, $unknownNsid);

        $this->assertSame(0, $stats['photos']['db']);
        $this->assertNull($stats['photos']['in_api']);
    }

    public function test_download_counts_for_counts_from_status_without_local_disk_check(): void
    {
        $connection = $this->createFlickrConnection();
        $ownerNsid = FlickrNsid::fake();

        StoredFile::query()->create([
            'source_id' => 'photo-1',
            'source_owner' => $ownerNsid,
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/'.$ownerNsid.'/photos/photo-1_secret.jpg',
            'original_name' => 'photo-1.jpg',
        ]);

        StoredFile::query()->create([
            'source_id' => 'photo-2',
            'source_owner' => $ownerNsid,
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/'.$ownerNsid.'/photos/photo-2_secret.jpg',
            'original_name' => 'photo-2.jpg',
        ]);

        StoredFile::query()->create([
            'source_id' => 'photo-3',
            'source_owner' => $ownerNsid,
            'variant' => 'original',
            'status' => 'failed',
            'original_name' => 'photo-3.jpg',
        ]);

        $counts = app(ContactStatsService::class)->downloadCountsFor($connection, [$ownerNsid]);

        $this->assertSame(2, $counts[$ownerNsid]['total']);
        $this->assertSame(1, $counts[$ownerNsid]['failed']);
        $this->assertFalse($counts[$ownerNsid]['processing']);
    }

    public function test_download_counts_for_marks_running_batches_as_processing(): void
    {
        $connection = $this->createFlickrConnection();
        $ownerNsid = FlickrNsid::fake();

        TransferBatch::query()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $ownerNsid,
            'status' => 'running',
            'total_count' => 10,
            'completed_count' => 4,
        ]);

        $counts = app(ContactStatsService::class)->downloadCountsFor($connection, [$ownerNsid]);

        $this->assertTrue($counts[$ownerNsid]['processing']);
        $this->assertSame(4, $counts[$ownerNsid]['batch_completed']);
        $this->assertSame(10, $counts[$ownerNsid]['batch_total']);
    }
}

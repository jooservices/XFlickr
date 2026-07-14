<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\ContactFactory;
use Database\Factories\Crawler\PhotoFactory;
use Modules\Contacts\Services\ContactCatalogDetailStatsService;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Models\Photo;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactCatalogDetailStatsServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_for_contact_returns_db_counts_and_api_totals(): void
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

        $photosRun = CrawlRun::query()->create([
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

        $stats = app(ContactCatalogDetailStatsService::class)->forContact($connection, $contactNsid);

        $this->assertSame(1, $stats['photos']['db']);
        $this->assertSame(1, $stats['photos']['with_sizes']);
        $this->assertSame(12, $stats['photos']['in_api']);
        $this->assertSame(4, $stats['photosets']['in_api']);
        $this->assertSame(2, $stats['galleries']['in_api']);
        $this->assertSame(7, $stats['favorites']['in_api']);
        $this->assertSame($photosRun->id, $photosRun->fresh()->id);
    }

    public function test_for_contact_returns_zero_counts_when_contact_is_unknown(): void
    {
        $connection = $this->createFlickrConnection();
        $unknownNsid = FlickrNsid::fake();

        $stats = app(ContactCatalogDetailStatsService::class)->forContact($connection, $unknownNsid);

        $this->assertSame(0, $stats['photos']['db']);
        $this->assertNull($stats['photos']['in_api']);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

use Modules\Contacts\Services\ContactCrawlStateService;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Support\XFlickrConfig;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactCrawlStateServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_for_contacts_returns_empty_state_for_empty_input(): void
    {
        $connection = $this->createFlickrConnection();

        $states = app(ContactCrawlStateService::class)->forContacts($connection, []);

        $this->assertSame([], $states);
    }

    public function test_for_contact_marks_completed_runs_as_crawled_with_fetched_counts(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();

        CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'photos',
            'status' => CrawlRunStatus::Completed->value,
        ]);
        CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'photosets',
            'status' => CrawlRunStatus::Completed->value,
        ]);

        $catalogCounts = [
            $contactNsid => [
                'photos' => 12,
                'photosets' => 4,
                'galleries' => 0,
                'favorites' => 0,
            ],
        ];

        $state = app(ContactCrawlStateService::class)->forContact($connection, $contactNsid, $catalogCounts);

        $this->assertFalse($state['photos']['processing']);
        $this->assertTrue($state['photos']['crawled']);
        $this->assertSame(12, $state['photos']['fetched']);
        $this->assertFalse($state['photosets']['processing']);
        $this->assertTrue($state['photosets']['crawled']);
        $this->assertSame(4, $state['photosets']['fetched']);
        $this->assertFalse($state['galleries']['crawled']);
        $this->assertFalse($state['favorites']['crawled']);
    }

    public function test_for_contact_marks_running_runs_as_processing_with_total_estimate(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();
        $perPage = XFlickrConfig::crawlInt('per_page', 500);

        $photosRun = CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'photos',
            'status' => CrawlRunStatus::Running->value,
        ]);
        CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $photosRun->id,
            'task_type' => TaskType::PeoplePhotos,
            'subject_nsid' => $contactNsid,
            'status' => CrawlStatus::Completed,
            'last_result_count' => 25,
        ]);
        CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $photosRun->id,
            'task_type' => TaskType::PeoplePhotos,
            'subject_nsid' => $contactNsid,
            'status' => CrawlStatus::Pending,
            'last_result_count' => 0,
        ]);

        $catalogCounts = [
            $contactNsid => [
                'photos' => 7,
                'photosets' => 0,
                'galleries' => 0,
                'favorites' => 0,
            ],
        ];

        $state = app(ContactCrawlStateService::class)->forContact($connection, $contactNsid, $catalogCounts);

        $this->assertTrue($state['photos']['processing']);
        $this->assertFalse($state['photos']['crawled']);
        $this->assertSame(7, $state['photos']['fetched']);
        $this->assertSame(25 + $perPage, $state['photos']['total']);
    }

    public function test_running_state_takes_precedence_over_completed_for_same_crawl_type(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();

        CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'photos',
            'status' => CrawlRunStatus::Completed->value,
        ]);
        CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'photos',
            'status' => CrawlRunStatus::Running->value,
        ]);

        $catalogCounts = [
            $contactNsid => [
                'photos' => 3,
                'photosets' => 0,
                'galleries' => 0,
                'favorites' => 0,
            ],
        ];

        $state = app(ContactCrawlStateService::class)->forContact($connection, $contactNsid, $catalogCounts);

        $this->assertTrue($state['photos']['processing']);
        $this->assertFalse($state['photos']['crawled']);
        $this->assertSame(3, $state['photos']['fetched']);
    }
}

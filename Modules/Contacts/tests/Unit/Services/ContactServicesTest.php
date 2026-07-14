<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\ContactFactory;
use Database\Factories\Crawler\PhotoFactory;
use Modules\Contacts\Services\ContactCrawlStateService;
use Modules\Contacts\Services\ContactListSorter;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\Contact;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Models\Photo;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactServicesTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_contact_list_sorter_orders_by_username_by_default(): void
    {
        $connection = $this->createFlickrConnection();
        $beta = $this->createContact(['username' => 'beta_user']);
        $alpha = $this->createContact(['username' => 'alpha_user']);

        foreach ([$alpha, $beta] as $contact) {
            ConnectionContactFactory::new()->create([
                'connection_key' => $connection->connection_key,
                'contact_nsid' => $contact->nsid,
            ]);
        }

        $sorted = app(ContactListSorter::class)
            ->apply(Contact::query()->whereIn('nsid', [$alpha->nsid, $beta->nsid]), $connection, 'not-a-column', 'asc')
            ->pluck('username')
            ->all();

        $this->assertSame(['alpha_user', 'beta_user'], $sorted);
    }

    public function test_contact_list_sorter_orders_by_photos_count_desc(): void
    {
        $connection = $this->createFlickrConnection();
        $busy = $this->createContact(['username' => 'busy']);
        $quiet = $this->createContact(['username' => 'quiet']);

        foreach ([$busy, $quiet] as $contact) {
            ConnectionContactFactory::new()->create([
                'connection_key' => $connection->connection_key,
                'contact_nsid' => $contact->nsid,
            ]);
        }

        Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), ['owner_nsid' => $busy->nsid]));
        Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), ['owner_nsid' => $busy->nsid]));
        Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), ['owner_nsid' => $quiet->nsid]));

        $sorted = app(ContactListSorter::class)
            ->apply(Contact::query()->whereIn('nsid', [$busy->nsid, $quiet->nsid]), $connection, 'photos_count', 'desc')
            ->pluck('username')
            ->all();

        $this->assertSame(['busy', 'quiet'], $sorted);
    }

    public function test_contact_crawl_state_marks_running_and_completed_types(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();

        $running = CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'photos',
            'status' => CrawlRunStatus::Running,
        ]);
        CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'galleries',
            'status' => CrawlRunStatus::Completed,
        ]);

        CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $running->id,
            'task_type' => TaskType::PeoplePhotos,
            'subject_nsid' => $contactNsid,
            'status' => CrawlStatus::Processing,
            'last_result_count' => 0,
        ]);

        $state = app(ContactCrawlStateService::class)->forContact(
            $connection,
            $contactNsid,
            [
                $contactNsid => [
                    'photos' => 4,
                    'photosets' => 0,
                    'galleries' => 2,
                    'favorites' => 0,
                ],
            ],
        );

        $this->assertTrue($state['photos']['processing']);
        $this->assertSame(4, $state['photos']['fetched']);
        $this->assertTrue($state['galleries']['crawled']);
        $this->assertSame(2, $state['galleries']['fetched']);
        $this->assertFalse($state['photosets']['processing']);
    }

    public function test_contact_crawl_state_returns_empty_map_for_no_nsids(): void
    {
        $connection = $this->createFlickrConnection();

        $this->assertSame([], app(ContactCrawlStateService::class)->forContacts($connection, []));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createContact(array $attributes = []): Contact
    {
        return Contact::query()->forceCreate(array_merge(ContactFactory::new()->definition(), $attributes));
    }
}

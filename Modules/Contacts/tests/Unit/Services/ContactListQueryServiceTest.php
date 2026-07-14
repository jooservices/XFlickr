<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

use Modules\Contacts\Models\ContactAnnotation;
use Modules\Contacts\Services\ContactListQueryService;
use Modules\Crawler\Models\ConnectionContact;
use Modules\Crawler\Models\Contact;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactListQueryServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_it_lists_nsids_for_connection_with_search_filter(): void
    {
        $connection = $this->createFlickrConnection();
        $matchNsid = FlickrNsid::fake();
        $otherNsid = FlickrNsid::fake();

        foreach ([[$matchNsid, 'alice'], [$otherNsid, 'bob']] as [$nsid, $username]) {
            Contact::query()->forceCreate([
                'nsid' => $nsid,
                'username' => $username,
                'realname' => $username,
            ]);
            ConnectionContact::query()->forceCreate([
                'connection_key' => $connection->connection_key,
                'contact_nsid' => $nsid,
            ]);
        }

        $nsids = app(ContactListQueryService::class)->listNsidsForConnection(
            $connection,
            search: 'alice',
        );

        $this->assertSame([$matchNsid], $nsids);
    }

    public function test_it_filters_starred_contacts_with_search(): void
    {
        $connection = $this->createFlickrConnection();
        $starredMatchNsid = FlickrNsid::fake();
        $starredOtherNsid = FlickrNsid::fake();
        $unstarredNsid = FlickrNsid::fake();

        foreach ([
            [$starredMatchNsid, 'alice-starred'],
            [$starredOtherNsid, 'bob-starred'],
            [$unstarredNsid, 'alice-plain'],
        ] as [$nsid, $username]) {
            Contact::query()->forceCreate([
                'nsid' => $nsid,
                'username' => $username,
                'realname' => $username,
            ]);
            ConnectionContact::query()->forceCreate([
                'connection_key' => $connection->connection_key,
                'contact_nsid' => $nsid,
            ]);
        }

        foreach ([$starredMatchNsid, $starredOtherNsid] as $nsid) {
            ContactAnnotation::factory()->starred()->create([
                'connection_key' => $connection->connection_key,
                'contact_nsid' => $nsid,
            ]);
        }

        $service = app(ContactListQueryService::class);

        $nsids = $service->listNsidsForConnection($connection, search: 'alice', starredOnly: true);
        $this->assertSame([$starredMatchNsid], $nsids);

        $page = $service->paginateForConnection(
            $connection,
            search: 'alice',
            sort: 'nsid',
            direction: 'asc',
            perPage: 10,
            page: 1,
            starredOnly: true,
        );

        $this->assertCount(1, $page->items());
        $this->assertSame($starredMatchNsid, $page->items()[0]->nsid);
    }

    public function test_starred_only_returns_empty_when_no_star_annotations_exist(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();

        Contact::query()->forceCreate([
            'nsid' => $contactNsid,
            'username' => fake()->userName(),
            'realname' => fake()->name(),
        ]);
        ConnectionContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contactNsid,
        ]);

        $service = app(ContactListQueryService::class);

        $this->assertSame([], $service->listNsidsForConnection($connection, starredOnly: true));

        $page = $service->paginateForConnection(
            $connection,
            search: null,
            sort: 'nsid',
            direction: 'asc',
            perPage: 10,
            page: 1,
            starredOnly: true,
        );

        $this->assertCount(0, $page->items());
        $this->assertSame(0, $page->total());
    }
}

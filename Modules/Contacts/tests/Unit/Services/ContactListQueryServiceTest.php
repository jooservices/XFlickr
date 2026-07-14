<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

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
}

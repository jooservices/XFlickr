<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

use Modules\Contacts\Services\ContactsService;
use Modules\Crawler\Models\ConnectionContact;
use Modules\Crawler\Models\Contact;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactsServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_list_nsids_for_connection_returns_matching_contact_nsids(): void
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

        $nsids = app(ContactsService::class)->listNsidsForConnection($connection, search: 'alice');

        $this->assertSame([$matchNsid], $nsids);
    }
}

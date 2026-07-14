<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Controllers;

use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class ContactsControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_index_redirects_to_connections_when_no_active_flickr_account(): void
    {
        $response = $this->get(route('contacts.index'));

        $response->assertRedirect(route('connections.index', ['provider' => 'flickr']));
        $response->assertSessionHas('error', 'Connect a Flickr account before viewing contacts.');
    }

    public function test_index_redirects_to_active_account_contacts(): void
    {
        $connection = $this->createFlickrConnection(['is_active' => true]);

        $response = $this->get(route('contacts.index'));

        $response->assertRedirect(route('flickr.accounts.contacts.index', $connection));
    }
}

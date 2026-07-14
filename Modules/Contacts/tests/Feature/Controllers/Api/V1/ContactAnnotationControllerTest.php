<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Controllers\Api\V1;

use Modules\Contacts\Models\ContactAnnotation;
use Modules\Crawler\Models\ConnectionContact;
use Modules\Crawler\Models\Contact;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class ContactAnnotationControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_contact_annotation_can_be_updated_via_api(): void
    {
        $connection = $this->createFlickrConnection();
        $contact = Contact::query()->forceCreate([
            'nsid' => '111@N01',
            'username' => 'alpha',
            'realname' => 'Alpha',
        ]);

        ConnectionContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
        ]);

        $response = $this->patchJson(
            '/api/v1/flickr/accounts/'.$connection->public_id.'/contacts/'.$contact->nsid.'/annotation',
            [
                'note' => 'Met at photo walk',
                'starred' => true,
            ],
        );

        $response->assertOk();
        $response->assertJsonPath('data.nsid', $contact->nsid);
        $response->assertJsonPath('data.note', 'Met at photo walk');
        $response->assertJsonPath('data.starred', true);

        $this->assertDatabaseHas('contact_annotations', [
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
            'note' => 'Met at photo walk',
        ]);

        $annotation = ContactAnnotation::query()->first();
        $this->assertNotNull($annotation?->starred_at);
    }

    public function test_contacts_index_includes_annotation_fields(): void
    {
        $connection = $this->createFlickrConnection();
        $contact = Contact::query()->forceCreate([
            'nsid' => '222@N01',
            'username' => 'beta',
            'realname' => 'Beta',
        ]);

        ConnectionContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
        ]);

        ContactAnnotation::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
            'note' => 'Local note',
            'starred_at' => now(),
        ]);

        $response = $this->get('/flickr/accounts/'.$connection->public_id.'/contacts');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Contacts/Index')
            ->where('contacts.0.nsid', $contact->nsid)
            ->where('contacts.0.starred', true)
            ->where('contacts.0.note', 'Local note'));
    }

    public function test_starred_only_filter_limits_contacts(): void
    {
        $connection = $this->createFlickrConnection();

        foreach (['111@N01', '222@N01'] as $index => $nsid) {
            Contact::query()->forceCreate([
                'nsid' => $nsid,
                'username' => 'user'.$index,
                'realname' => 'User '.$index,
            ]);

            ConnectionContact::query()->forceCreate([
                'connection_key' => $connection->connection_key,
                'contact_nsid' => $nsid,
            ]);
        }

        ContactAnnotation::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => '111@N01',
            'starred_at' => now(),
        ]);

        $response = $this->get('/flickr/accounts/'.$connection->public_id.'/contacts?starred_only=1');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Contacts/Index')
            ->where('filters.starred_only', true)
            ->has('contacts', 1)
            ->where('contacts.0.nsid', '111@N01'));
    }
}

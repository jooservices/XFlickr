<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Controllers\Api\V1;

use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\ContactFactory;
use Illuminate\Support\Facades\Bus;
use Modules\Crawler\Jobs\FetchPeoplePhotosJob;
use Modules\Crawler\Models\Contact;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class ContactControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_progress_returns_empty_payload_without_nsids(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/contacts/progress');

        $response->assertOk();
        $response->assertJsonPath('data.contacts', []);
    }

    public function test_suggest_returns_empty_for_short_search(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/contacts/suggest?search=a');

        $response->assertOk();
        $response->assertJsonPath('data', []);
    }

    public function test_suggest_returns_matching_contacts(): void
    {
        $connection = $this->createFlickrConnection();
        $contact = Contact::query()->forceCreate(array_merge(ContactFactory::new()->definition(), [
            'username' => 'findable_user',
        ]));

        ConnectionContactFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
        ]);

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/contacts/suggest?search=findable');

        $response->assertOk();
        $response->assertJsonPath('data.0.nsid', $contact->nsid);
        $response->assertJsonPath('data.0.username', 'findable_user');
    }

    public function test_store_crawl_run_accepts_valid_types(): void
    {
        Bus::fake([FetchPeoplePhotosJob::class]);

        $connection = $this->createFlickrConnection();
        $contact = Contact::query()->forceCreate(ContactFactory::new()->definition());

        ConnectionContactFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
        ]);

        $response = $this->postJson(
            '/api/v1/flickr/accounts/'.$connection->public_id.'/contacts/'.$contact->nsid.'/crawl-runs',
            ['types' => ['photos']],
        );

        $response->assertAccepted();
        $response->assertJsonPath('message', 'Contact crawl started.');
    }

    public function test_store_crawl_run_rejects_invalid_types(): void
    {
        $connection = $this->createFlickrConnection();
        $contact = Contact::query()->forceCreate(ContactFactory::new()->definition());

        $response = $this->postJson(
            '/api/v1/flickr/accounts/'.$connection->public_id.'/contacts/'.$contact->nsid.'/crawl-runs',
            ['types' => ['not-valid']],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['types.0']);
    }
}

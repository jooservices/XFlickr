<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Controllers\Api\V1;

use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\ContactFactory;
use Illuminate\Support\Facades\Bus;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Jobs\FetchCrawlPageJob;
use Modules\Crawler\Models\Contact;
use Modules\Crawler\Services\FlickrClientFactory;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
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
        Bus::fake([FetchCrawlPageJob::class]);

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

    public function test_import_resolves_url_links_contact_and_starts_crawl(): void
    {
        Bus::fake([FetchCrawlPageJob::class]);

        $this->seedFlickrAppCredentials();
        $connection = $this->createFlickrConnection(['app_profile' => 'main']);
        $nsid = FlickrNsid::fake();

        $this->app->instance(
            FlickrClientFactory::class,
            new FlickrClientFactory(
                FakeFlickrTransport::new()
                    ->pushJson([
                        'stat' => 'ok',
                        'user' => ['id' => $nsid],
                    ])
                    ->pushJson([
                        'stat' => 'ok',
                        'person' => [
                            'username' => ['_content' => 'imported-user'],
                            'realname' => ['_content' => 'Imported User'],
                        ],
                    ]),
            ),
        );

        $response = $this->postJson(
            '/api/v1/flickr/accounts/'.$connection->public_id.'/contacts',
            [
                'source' => 'url',
                'url' => 'https://www.flickr.com/photos/imported-user/',
                'start_crawl' => true,
                'types' => ['photos'],
            ],
        );

        $response->assertOk();
        $response->assertJsonPath('data.nsid', $nsid);
        $response->assertJsonPath('data.already_linked', false);
        $response->assertJsonPath('data.crawl_started', true);
        $response->assertJsonPath(
            'data.redirect_path',
            route('flickr.accounts.contacts.show', [
                'connection' => $connection->public_id,
                'contactNsid' => $nsid,
            ], absolute: false),
        );

        $this->assertDatabaseHas('xflickr_connection_contacts', [
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $nsid,
        ]);
    }

    public function test_import_rejects_invalid_host(): void
    {
        $this->seedFlickrAppCredentials();
        $connection = $this->createFlickrConnection(['app_profile' => 'main']);

        $response = $this->postJson(
            '/api/v1/flickr/accounts/'.$connection->public_id.'/contacts',
            ['source' => 'url', 'url' => 'https://example.com/people/x', 'start_crawl' => false],
        );

        $response->assertUnprocessable();
    }

    public function test_import_requires_source_url(): void
    {
        $this->seedFlickrAppCredentials();
        $connection = $this->createFlickrConnection(['app_profile' => 'main']);

        $response = $this->postJson(
            '/api/v1/flickr/accounts/'.$connection->public_id.'/contacts',
            ['url' => 'https://www.flickr.com/photos/someone/'],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['source']);
    }

    private function seedFlickrAppCredentials(): void
    {
        RuntimeConfig::set('xflickr_app.main', [
            'apiKey' => 'test-api-key-12345',
            'apiSecret' => 'test-api-secret',
            'callbackUrl' => 'http://localhost/flickr/callback',
        ], 'json');
        RuntimeConfig::refresh();
    }
}

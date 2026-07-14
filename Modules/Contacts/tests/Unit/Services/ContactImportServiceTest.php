<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\ContactFactory;
use Illuminate\Support\Facades\Bus;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Contacts\Services\ContactImportService;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Jobs\FetchCrawlPageJob;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Flickr\Exceptions\FlickrUrlResolutionException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactImportServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedFlickrAppCredentials();
    }

    public function test_import_persists_contact_and_starts_default_crawl_types(): void
    {
        Bus::fake([FetchCrawlPageJob::class]);

        $connection = $this->createFlickrConnection(['app_profile' => 'main']);
        $nsid = FlickrNsid::fake();
        $this->bindFakeLookup($nsid, 'imported-user', 'Imported User');

        $result = app(ContactImportService::class)->import(
            $connection,
            'https://www.flickr.com/photos/imported-user/',
        );

        $this->assertSame($nsid, $result->nsid);
        $this->assertSame('imported-user', $result->username);
        $this->assertSame('Imported User', $result->realname);
        $this->assertFalse($result->alreadyLinked);
        $this->assertTrue($result->crawlStarted);
        $this->assertDatabaseHas('xflickr_connection_contacts', [
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $nsid,
        ]);
        Bus::assertDispatched(FetchCrawlPageJob::class);
    }

    public function test_import_uses_custom_crawl_types_and_can_skip_crawl(): void
    {
        Bus::fake([FetchCrawlPageJob::class]);

        $connection = $this->createFlickrConnection(['app_profile' => 'main']);
        $nsid = FlickrNsid::fake();
        $this->bindFakeLookup($nsid, 'skip-crawl', null);

        $result = app(ContactImportService::class)->import(
            $connection,
            'https://www.flickr.com/photos/skip-crawl/',
            startCrawl: false,
            crawlTypes: [CrawlType::Photos],
        );

        $this->assertFalse($result->crawlStarted);
        $this->assertSame('skip-crawl', $result->username);
        $this->assertNull($result->realname);
        Bus::assertNotDispatched(FetchCrawlPageJob::class);
    }

    public function test_import_marks_already_linked_when_contact_exists(): void
    {
        Bus::fake([FetchCrawlPageJob::class]);

        $connection = $this->createFlickrConnection(['app_profile' => 'main']);
        $nsid = FlickrNsid::fake();
        ContactFactory::new()->create(['nsid' => $nsid, 'username' => 'linked-user']);
        ConnectionContactFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $nsid,
        ]);
        $this->bindFakeLookup($nsid, 'linked-user', null);

        $result = app(ContactImportService::class)->import(
            $connection,
            'https://www.flickr.com/photos/linked-user/',
            startCrawl: false,
        );

        $this->assertTrue($result->alreadyLinked);
        $this->assertFalse($result->crawlStarted);
    }

    public function test_import_rethrows_url_resolution_failure(): void
    {
        $connection = $this->createFlickrConnection(['app_profile' => 'main']);

        $this->expectException(FlickrUrlResolutionException::class);

        app(ContactImportService::class)->import(
            $connection,
            'https://example.com/people/x',
            startCrawl: false,
        );
    }

    private function bindFakeLookup(string $nsid, string $username, ?string $realname): void
    {
        $person = [
            'username' => ['_content' => $username],
        ];
        if ($realname !== null) {
            $person['realname'] = ['_content' => $realname];
        }

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
                        'person' => $person,
                    ]),
            ),
        );
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

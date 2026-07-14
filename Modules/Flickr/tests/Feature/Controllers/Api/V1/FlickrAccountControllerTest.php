<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Feature\Controllers\Api\V1;

use Illuminate\Support\Facades\Queue;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Enums\CrawlType;
use Modules\Flickr\Exceptions\GlobalCrawlPauseException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class FlickrAccountControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    protected function tearDown(): void
    {
        if (app()->bound('config-store') && RuntimeConfig::has('xflickr.global_pause')) {
            RuntimeConfig::forget('xflickr.global_pause');
            RuntimeConfig::refresh();
        }

        parent::tearDown();
    }

    public function test_show_returns_account_payload(): void
    {
        $connection = $this->createFlickrConnection([
            'username' => 'api-user',
            'fullname' => 'API User',
        ]);

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id);

        $response->assertOk();
        $response->assertJsonPath('data.account.nsid', $connection->connection_key);
        $response->assertJsonPath('data.account.username', 'api-user');
        $response->assertJsonPath('data.account.fullname', 'API User');
    }

    public function test_store_crawl_run_accepts_crawl_types(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();
        $subjectNsid = FlickrNsid::fake();

        $response = $this->postJson('/api/v1/flickr/accounts/'.$connection->public_id.'/crawl-runs', [
            'types' => ['photos', 'contacts'],
            'subject_nsid' => $subjectNsid,
        ]);

        $response->assertAccepted();
        $response->assertJsonPath('message', 'Crawl started.');
        $this->assertDatabaseHas('xflickr_crawl_runs', [
            'connection_key' => $connection->connection_key,
            'crawl_type' => CrawlType::Photos->value,
        ]);
        $this->assertDatabaseHas('xflickr_crawl_runs', [
            'connection_key' => $connection->connection_key,
            'crawl_type' => CrawlType::Contacts->value,
        ]);
    }

    public function test_store_crawl_run_returns_unprocessable_when_token_is_invalid(): void
    {
        $connection = $this->createFlickrConnection();

        $this->mockFlickrTokenHealth(valid: false);

        $response = $this->postJson('/api/v1/flickr/accounts/'.$connection->public_id.'/crawl-runs', [
            'types' => ['photos'],
        ]);

        $response->assertUnprocessable();
    }

    public function test_store_crawl_run_returns_unprocessable_when_global_pause_is_active(): void
    {
        $connection = $this->createFlickrConnection();

        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $response = $this->postJson('/api/v1/flickr/accounts/'.$connection->public_id.'/crawl-runs', [
            'types' => ['photos'],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonFragment(['message' => GlobalCrawlPauseException::active()->getMessage()]);
    }

    public function test_store_crawl_run_validates_crawl_types(): void
    {
        $connection = $this->createFlickrConnection();

        $this->postJson('/api/v1/flickr/accounts/'.$connection->public_id.'/crawl-runs', [
            'types' => ['not-a-crawl-type'],
        ])->assertUnprocessable();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services;

use Illuminate\Support\Facades\Queue;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Enums\CrawlType;
use Modules\Flickr\Services\FlickrCrawlService;
use RuntimeException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class FlickrCrawlServiceTest extends TestCase
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

    public function test_connection_throws_when_token_payload_is_empty(): void
    {
        $connection = $this->createFlickrConnection([
            'token_payload' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has no token payload');

        app(FlickrCrawlService::class)->connection($connection);
    }

    public function test_crawl_starts_photos_run_for_subject(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();
        $subjectNsid = FlickrNsid::fake();

        $run = app(FlickrCrawlService::class)->crawl($connection, CrawlType::Photos, $subjectNsid);

        $this->assertSame(CrawlType::Photos->value, $run->crawl_type);
        $this->assertSame($connection->connection_key, $run->connection_key);
        $this->assertDatabaseHas('xflickr_crawl_runs', ['id' => $run->id]);
    }

    public function test_crawl_many_starts_each_requested_type(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();
        $subjectNsid = FlickrNsid::fake();

        $runs = app(FlickrCrawlService::class)->crawlMany(
            $connection,
            [CrawlType::Photosets, CrawlType::Galleries, CrawlType::Favorites],
            $subjectNsid,
        );

        $this->assertCount(3, $runs);
        $this->assertSame(
            [CrawlType::Photosets->value, CrawlType::Galleries->value, CrawlType::Favorites->value],
            array_map(fn ($run) => $run->crawl_type, $runs),
        );
    }

    public function test_contacts_crawl_uses_null_subject_for_account_owner(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();

        $run = app(FlickrCrawlService::class)->crawl(
            $connection,
            CrawlType::Contacts,
            $connection->connection_key,
        );

        $this->assertSame(CrawlType::Contacts->value, $run->crawl_type);
        $this->assertNull($run->subject_key);
    }

    public function test_contacts_crawl_uses_explicit_subject_nsid(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();
        $subjectNsid = FlickrNsid::fake();

        $run = app(FlickrCrawlService::class)->crawl(
            $connection,
            CrawlType::Contacts,
            $subjectNsid,
        );

        $this->assertSame($subjectNsid, $run->subject_nsid);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

use Modules\Contacts\Services\ContactGraphExpandService;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Models\CrawlRun;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactGraphExpandServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_expand_returns_existing_running_crawl_without_reexpand(): void
    {
        $connection = $this->createFlickrConnection();
        $subjectNsid = FlickrNsid::fake();

        $existing = CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'crawl_type' => CrawlType::Contacts->value,
            'subject_nsid' => $subjectNsid,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        $result = app(ContactGraphExpandService::class)->expand($connection, $subjectNsid);

        $this->assertSame((int) $existing->id, $result['crawl_run_id']);
        $this->assertFalse($result['reexpand']);
        $this->assertSame($subjectNsid, $result['subject_nsid']);
    }

    public function test_expand_starts_new_crawl_for_account_root_subject(): void
    {
        $connection = $this->createFlickrConnection();

        $result = app(ContactGraphExpandService::class)->expand($connection, $connection->connection_key);

        $this->assertTrue($result['reexpand']);
        $this->assertSame($connection->connection_key, $result['subject_nsid']);
        $this->assertDatabaseHas('xflickr_crawl_runs', [
            'id' => $result['crawl_run_id'],
            'connection_key' => $connection->connection_key,
            'crawl_type' => CrawlType::Contacts->value,
        ]);
    }
}

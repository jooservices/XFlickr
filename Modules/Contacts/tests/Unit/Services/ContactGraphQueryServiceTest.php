<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\ContactFactory;
use Database\Factories\Crawler\PhotoFactory;
use Modules\Contacts\Database\Factories\ContactAnnotationFactory;
use Modules\Contacts\Services\ContactGraphQueryService;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\SubjectContact;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactGraphQueryServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_snapshot_prioritizes_starred_contacts_within_direct_limit(): void
    {
        $connection = $this->createFlickrConnection();
        $starredNsid = FlickrNsid::fake();
        $popularNsid = FlickrNsid::fake();
        $otherNsid = FlickrNsid::fake();

        foreach ([[$starredNsid, 'starred'], [$popularNsid, 'popular'], [$otherNsid, 'other']] as [$nsid, $username]) {
            ContactFactory::new()->create([
                'nsid' => $nsid,
                'username' => $username,
                'realname' => $username,
            ]);
            ConnectionContactFactory::new()->create([
                'connection_key' => $connection->connection_key,
                'contact_nsid' => $nsid,
            ]);
        }

        PhotoFactory::new()->count(5)->create(['owner_nsid' => $popularNsid]);
        PhotoFactory::new()->create(['owner_nsid' => $otherNsid]);

        ContactAnnotationFactory::new()->starred()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $starredNsid,
            'note' => 'Keep visible',
        ]);

        $snapshot = app(ContactGraphQueryService::class)->snapshot($connection, directLimit: +2);

        $visibleNsids = array_column($snapshot['nodes'], 'nsid');
        $this->assertContains($starredNsid, $visibleNsids);
        $this->assertContains($popularNsid, $visibleNsids);
        $this->assertNotContains($otherNsid, $visibleNsids);
        $this->assertTrue($snapshot['meta']['has_more_direct']);
    }

    public function test_snapshot_includes_subject_edges_for_visible_contacts(): void
    {
        $connection = $this->createFlickrConnection();
        $parentNsid = FlickrNsid::fake();
        $childNsid = FlickrNsid::fake();

        foreach ([$parentNsid, $childNsid] as $nsid) {
            ContactFactory::new()->create([
                'nsid' => $nsid,
                'username' => $nsid,
                'realname' => $nsid,
            ]);
            ConnectionContactFactory::new()->create([
                'connection_key' => $connection->connection_key,
                'contact_nsid' => $nsid,
            ]);
        }

        SubjectContact::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $parentNsid,
            'contact_nsid' => $childNsid,
            'discovered_at' => now(),
        ]);

        $snapshot = app(ContactGraphQueryService::class)->snapshot($connection, directLimit: 10);

        $edgeTargets = array_column($snapshot['edges'], 'to');
        $this->assertContains($childNsid, $edgeTargets);
        $this->assertGreaterThan(0, $snapshot['meta']['subject_edges_shown']);
    }

    public function test_delta_for_root_subject_lists_direct_contacts(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();

        ContactFactory::new()->create([
            'nsid' => $contactNsid,
            'username' => 'delta-user',
            'realname' => 'Delta User',
        ]);
        ConnectionContactFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contactNsid,
        ]);

        $delta = app(ContactGraphQueryService::class)->delta(
            $connection,
            $connection->connection_key,
            sinceEdgeId: 0,
            crawlRunId: null,
        );

        $this->assertSame($contactNsid, $delta['edges'][0]['to']);
        $this->assertCount(1, $delta['nodes']);
        $this->assertTrue($delta['done']);
        $this->assertNull($delta['crawl_status']);
    }

    public function test_delta_reports_crawl_status_for_running_run(): void
    {
        $connection = $this->createFlickrConnection();
        $subjectNsid = FlickrNsid::fake();
        $contactNsid = FlickrNsid::fake();

        SubjectContact::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subjectNsid,
            'contact_nsid' => $contactNsid,
            'discovered_at' => now(),
        ]);

        $run = CrawlRun::query()->create([
            'connection_key' => $connection->connection_key,
            'crawl_type' => 'contacts',
            'subject_nsid' => $subjectNsid,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        $delta = app(ContactGraphQueryService::class)->delta(
            $connection,
            $subjectNsid,
            sinceEdgeId: 0,
            crawlRunId: $run->id,
        );

        $this->assertFalse($delta['done']);
        $this->assertSame(CrawlRunStatus::Running->value, $delta['crawl_status']);
    }
}

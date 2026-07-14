<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Repositories;

use Modules\Crawler\Models\SubjectContact;
use Modules\Crawler\Repositories\SubjectContactRepository;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class SubjectContactRepositoryTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_upsert_many_returns_zero_for_empty_input(): void
    {
        $repository = app(SubjectContactRepository::class);

        $this->assertSame(0, $repository->upsertMany('conn@N01', 'subject@N01', []));
    }

    public function test_upsert_many_skips_blank_nsids_and_deduplicates(): void
    {
        $connectionKey = FlickrNsid::fake();
        $subjectNsid = FlickrNsid::fake();
        $contactNsid = FlickrNsid::fake();

        $inserted = app(SubjectContactRepository::class)->upsertMany(
            $connectionKey,
            $subjectNsid,
            [$contactNsid, $contactNsid, ''],
            crawlRunId: 42,
            chunk: 1,
        );

        $this->assertSame(1, $inserted);
        $this->assertDatabaseHas('xflickr_subject_contacts', [
            'connection_key' => $connectionKey,
            'subject_nsid' => $subjectNsid,
            'contact_nsid' => $contactNsid,
            'crawl_run_id' => 42,
        ]);
    }

    public function test_discovered_helpers_return_sorted_contact_nsids(): void
    {
        $connectionKey = FlickrNsid::fake();
        $subjectNsid = FlickrNsid::fake();
        $first = '11111111@N01';
        $second = '99999999@N01';

        SubjectContact::query()->create([
            'connection_key' => $connectionKey,
            'subject_nsid' => $subjectNsid,
            'contact_nsid' => $second,
            'crawl_run_id' => 7,
            'discovered_at' => now(),
        ]);
        SubjectContact::query()->create([
            'connection_key' => $connectionKey,
            'subject_nsid' => $subjectNsid,
            'contact_nsid' => $first,
            'crawl_run_id' => 7,
            'discovered_at' => now(),
        ]);

        $repository = app(SubjectContactRepository::class);

        $this->assertSame([$first, $second], $repository->discoveredForCrawlRun(7));
        $this->assertSame([$first, $second], $repository->discoveredContactNsids($connectionKey, $subjectNsid));
    }
}

<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Fetchers;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\Flickr\DTO\Common\PaginationData;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Fetchers\SubjectContactsFetcher;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Models\SubjectContact;
use Modules\Crawler\Services\FlickrCatalogService;
use Modules\Crawler\Tests\TestCase;
use Tests\Support\FlickrNsid;

final class SubjectContactsFetcherTest extends TestCase
{
    public function test_persists_subject_contacts_and_schedules_next_page(): void
    {
        $connectionKey = FlickrNsid::fake();
        $subjectNsid = FlickrNsid::fake();
        $contactNsid = FlickrNsid::fake();

        $run = CrawlRun::query()->create([
            'connection_key' => $connectionKey,
            'crawl_type' => 'subject_contacts',
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        $target = CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::SubjectContactsPage,
            'subject_nsid' => $subjectNsid,
            'page' => 1,
            'status' => 'pending',
        ]);
        $target->setRelation('crawlRun', $run);

        $response = new ApiResponseData(
            ok: true,
            data: [
                'contacts' => [
                    'contact' => [
                        [
                            'nsid' => $contactNsid,
                            'username' => fake()->userName(),
                            'realname' => fake()->name(),
                            'friend' => '1',
                            'family' => '0',
                        ],
                    ],
                ],
            ],
            pagination: new PaginationData(page: 1, pages: 2, perPage: 500, total: 2),
        );

        $result = (new SubjectContactsFetcher(app(FlickrCatalogService::class)))
            ->fetchPage($target, $response);

        $this->assertSame(1, $result->resultCount);
        $this->assertCount(1, $result->followUpSpecs);
        $this->assertSame(TaskType::SubjectContactsPage, $result->followUpSpecs[0]->taskType);
        $this->assertSame(2, $result->followUpSpecs[0]->page);
        $this->assertSame($subjectNsid, $result->followUpSpecs[0]->subjectNsid);

        $this->assertSame(1, SubjectContact::query()
            ->where('connection_key', $connectionKey)
            ->where('subject_nsid', $subjectNsid)
            ->where('contact_nsid', $contactNsid)
            ->count());
    }

    public function test_last_page_does_not_schedule_follow_up(): void
    {
        $run = CrawlRun::query()->create([
            'connection_key' => FlickrNsid::fake(),
            'crawl_type' => 'subject_contacts',
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        $target = CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::SubjectContactsPage,
            'subject_nsid' => FlickrNsid::fake(),
            'page' => 2,
            'status' => 'pending',
        ]);
        $target->setRelation('crawlRun', $run);

        $response = new ApiResponseData(
            ok: true,
            data: ['contacts' => ['contact' => []]],
            pagination: new PaginationData(page: 2, pages: 2, perPage: 500, total: 0),
        );

        $result = (new SubjectContactsFetcher(app(FlickrCatalogService::class)))
            ->fetchPage($target, $response);

        $this->assertSame(0, $result->resultCount);
        $this->assertSame([], $result->followUpSpecs);
    }

    public function test_empty_connection_key_is_tolerated_without_crash(): void
    {
        $target = new CrawlTarget([
            'task_type' => TaskType::SubjectContactsPage,
            'subject_nsid' => FlickrNsid::fake(),
            'page' => 1,
        ]);
        $target->setRelation('crawlRun', null);

        $response = new ApiResponseData(
            ok: true,
            data: ['contacts' => ['contact' => []]],
            pagination: null,
        );

        $result = (new SubjectContactsFetcher(app(FlickrCatalogService::class)))
            ->fetchPage($target, $response);

        $this->assertSame(0, $result->resultCount);
        $this->assertSame([], $result->followUpSpecs);
    }
}

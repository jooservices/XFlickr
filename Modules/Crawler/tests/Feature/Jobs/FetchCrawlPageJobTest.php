<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Feature\Jobs;

use Illuminate\Support\Facades\Queue;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Jobs\FetchCrawlPageJob;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Tests\TestCase;

final class FetchCrawlPageJobTest extends TestCase
{
    public function test_job_fetches_contacts_and_completes_target(): void
    {
        $run = CrawlRun::query()->create([
            'connection_key' => 'job-conn',
            'crawl_type' => 'contacts',
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        Connection::query()->create([
            'connection_key' => 'job-conn',
            'app_profile' => 'default',
            'token_payload' => $this->sampleToken(),
        ]);

        $target = CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::ContactsPage,
            'page' => 1,
            'status' => CrawlStatus::Pending,
        ]);

        $transport = FakeFlickrTransport::new()->pushJson([
            'stat' => 'ok',
            'contacts' => [
                'page' => 1,
                'pages' => 1,
                'perpage' => 500,
                'total' => 1,
                'contact' => [
                    [
                        'nsid' => '999@N01',
                        'username' => 'job-user',
                        'friend' => 0,
                        'family' => 0,
                    ],
                ],
            ],
        ]);

        $clients = new FlickrClientFactory($transport);

        $this->runContactsPageJob($target->id, clients: $clients);

        $target->refresh();
        $this->assertSame(CrawlStatus::Completed, $target->status);
        $this->assertDatabaseHas('xflickr_contacts', ['nsid' => '999@N01']);
        $this->assertDatabaseHas('xflickr_api_logs', ['connection_key' => 'job-conn']);
    }

    public function test_job_dispatches_follow_up_page_without_scheduler(): void
    {
        Queue::fake();

        $run = CrawlRun::query()->create([
            'connection_key' => 'job-follow',
            'crawl_type' => 'contacts',
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        Connection::query()->create([
            'connection_key' => 'job-follow',
            'app_profile' => 'default',
            'token_payload' => $this->sampleToken(),
        ]);

        $target = CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::ContactsPage,
            'page' => 1,
            'status' => CrawlStatus::Pending,
        ]);

        $transport = FakeFlickrTransport::new()->pushJson([
            'stat' => 'ok',
            'contacts' => [
                'page' => 1,
                'pages' => 2,
                'perpage' => 500,
                'total' => 2,
                'contact' => [
                    ['nsid' => '999@N01', 'username' => 'job-user', 'friend' => 0, 'family' => 0],
                ],
            ],
        ]);

        $this->runContactsPageJob($target->id, clients: new FlickrClientFactory($transport));

        $pageTwo = CrawlTarget::query()
            ->where('xflickr_crawl_run_id', $run->id)
            ->where('page', 2)
            ->first();

        $this->assertNotNull($pageTwo);
        $this->assertSame(CrawlStatus::Queued, $pageTwo->status);
        Queue::assertPushed(FetchCrawlPageJob::class, 1);
    }
}

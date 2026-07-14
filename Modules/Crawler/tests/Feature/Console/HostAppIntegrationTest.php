<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Feature\Console;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Facades\FlickrService;
use Modules\Crawler\Jobs\FetchContactsPageJob;
use Modules\Crawler\Jobs\FetchPeoplePhotosJob;
use Modules\Crawler\Services\FlickrRequestLimiter;
use Modules\Crawler\Tests\TestCase;

final class HostAppIntegrationTest extends TestCase
{
    private string $connectionKey = 'user-42';

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionKey = 'host-integration-'.uniqid();
    }

    public function test_flickr_service_dispatches_to_configured_queue(): void
    {
        Queue::fake();

        config()->set('xflickr-crawler.queue', 'xflickr');

        FlickrService::connection($this->connectionKey, $this->sampleToken(), appProfile: 'default')
            ->contacts();

        Queue::assertPushedOn('xflickr', FetchContactsPageJob::class);
    }

    public function test_same_connection_key_shares_rate_limit_across_subject_nsids(): void
    {
        $this->requiresRedis();

        $limiter = app(FlickrRequestLimiter::class);
        $key = $this->connectionKey;

        Redis::del(
            "xflickr:req:{$key}:window",
            "xflickr:req:{$key}:last",
            "xflickr:pause:{$key}",
        );

        config()->set('xflickr-crawler.throttle.max_requests_per_hour', 2);
        config()->set('xflickr-crawler.throttle.min_gap_ms', 0);

        Queue::fake();

        FlickrService::connection($key, $this->sampleToken())->contacts();
        FlickrService::connection($key, $this->sampleToken())->photos('999@N01');
        FlickrService::connection($key, $this->sampleToken())->photos('888@N01');

        $this->assertTrue($limiter->acquire($key)->acquired);
        $this->assertTrue($limiter->acquire($key)->acquired);
        $this->assertFalse($limiter->acquire($key)->acquired);

        $state = $limiter->state($key)->toArray();
        $this->assertSame($key, $state['connection_key']);
        $this->assertSame(2, $state['requests_used']);
    }

    public function test_multiple_crawl_types_enqueue_under_same_connection_key(): void
    {
        Queue::fake();

        $token = $this->sampleToken();
        $key = $this->connectionKey;

        $contactsRun = FlickrService::connection($key, $token)->contacts();
        $photosRun = FlickrService::connection($key, $token)->photos('999@N01');

        $this->assertDatabaseHas('xflickr_connections', [
            'connection_key' => $key,
        ]);

        $this->assertDatabaseHas('xflickr_crawl_targets', [
            'xflickr_crawl_run_id' => $contactsRun->id,
            'task_type' => TaskType::ContactsPage->value,
        ]);

        $this->assertDatabaseHas('xflickr_crawl_targets', [
            'xflickr_crawl_run_id' => $photosRun->id,
            'task_type' => TaskType::PeoplePhotos->value,
            'subject_nsid' => '999@N01',
        ]);

        Queue::assertPushed(FetchContactsPageJob::class);
        Queue::assertPushed(FetchPeoplePhotosJob::class);
    }

    public function test_dispatch_command_is_registered(): void
    {
        $this->artisan('xflickr:dispatch')
            ->assertSuccessful();
    }
}

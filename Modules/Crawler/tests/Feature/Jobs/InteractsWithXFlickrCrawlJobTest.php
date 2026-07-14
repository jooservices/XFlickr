<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Feature\Jobs;

use Illuminate\Support\Facades\Redis;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Jobs\FetchCrawlPageJob;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Services\FlickrApiAuditService;
use Modules\Crawler\Services\FlickrApiOutcomeClassifier;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Services\FlickrPermitAcquirer;
use Modules\Crawler\Services\FlickrRequestLimiter;
use Modules\Crawler\Services\FlickrSpiderService;
use Modules\Crawler\Tests\Support\StubFlickrPermitAcquirer;
use Modules\Crawler\Tests\Support\ThrowingFlickrTransport;
use Modules\Crawler\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FlickrNsid;

final class InteractsWithXFlickrCrawlJobTest extends TestCase
{
    public function test_people_photos_job_fails_target_when_api_throws(): void
    {
        [$target] = $this->prepareSubjectTarget(TaskType::PeoplePhotos);

        $this->app->instance(FlickrPermitAcquirer::class, new StubFlickrPermitAcquirer($this->grantedPermit()));

        (new FetchCrawlPageJob($target->id))->handle(
            new FlickrClientFactory(new ThrowingFlickrTransport('network down')),
            app(FlickrRequestLimiter::class),
            app(FlickrApiOutcomeClassifier::class),
            app(FlickrApiAuditService::class),
            app(FlickrSpiderService::class),
        );

        $target->refresh();
        $this->assertSame(CrawlStatus::Failed, $target->status);
        $this->assertSame('network down', $target->failed_reason);
    }

    /**
     * @return iterable<string, array{0: TaskType, 1: string, 2: string}>
     */
    public static function subjectNsidTaskTypeProvider(): iterable
    {
        yield 'people photos' => [TaskType::PeoplePhotos, 'photos', 'photo'];
        yield 'photosets list' => [TaskType::PhotosetsList, 'photosets', 'photoset'];
        yield 'galleries list' => [TaskType::GalleriesList, 'galleries', 'gallery'];
    }

    #[DataProvider('subjectNsidTaskTypeProvider')]
    public function test_job_fails_with_auth_token_invalid_on_empty_first_page(TaskType $taskType, string $containerKey, string $itemKey): void
    {
        [$target] = $this->prepareSubjectTarget($taskType);

        $transport = FakeFlickrTransport::new()
            ->pushJson([
                'stat' => 'ok',
                $containerKey => [
                    'page' => 1,
                    'pages' => 0,
                    'perpage' => 500,
                    'total' => 0,
                ],
            ])
            ->pushJson([
                'stat' => 'fail',
                'code' => 108,
                'message' => 'Invalid auth token',
            ]);

        $this->runCrawlPageJob($taskType, $target->id, clients: new FlickrClientFactory($transport));

        $target->refresh();
        $this->assertSame(CrawlStatus::Failed, $target->status);
        $this->assertSame('auth_token_invalid', $target->failed_reason);
    }

    public function test_job_fails_target_on_api_error_without_error_payload(): void
    {
        $run = CrawlRun::query()->create([
            'connection_key' => 'bare-fail-conn',
            'crawl_type' => 'contacts',
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        Connection::query()->create([
            'connection_key' => 'bare-fail-conn',
            'app_profile' => 'default',
            'token_payload' => $this->sampleToken(),
        ]);

        $target = CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::ContactsPage,
            'page' => 1,
            'status' => CrawlStatus::Pending,
        ]);

        $transport = FakeFlickrTransport::new()->pushJson(['stat' => 'fail']);

        $this->runContactsPageJob($target->id, clients: new FlickrClientFactory($transport));

        $target->refresh();
        $this->assertSame(CrawlStatus::Failed, $target->status);
        $this->assertNotEmpty($target->failed_reason);
    }

    public function test_throwable_rate_limit_releases_target_and_sets_cooldown(): void
    {
        $this->requiresRedis();

        $connectionKey = 'throw-rate-'.uniqid();
        $this->cleanLimiterKeys($connectionKey);

        [$target] = $this->prepareSubjectTarget(TaskType::PeoplePhotos, $connectionKey);

        $transport = FakeFlickrTransport::new()->pushJson([
            'stat' => 'fail',
            'code' => 99,
            'message' => 'Rate limit exceeded',
        ]);

        $this->runCrawlPageJob(
            TaskType::PeoplePhotos,
            $target->id,
            clients: new FlickrClientFactory($transport),
            useRealPermitAcquirer: true,
        );

        $target->refresh();
        $this->assertSame(CrawlStatus::Pending, $target->status);
        $this->assertGreaterThan(0, $target->retry_count);
        $this->assertNotNull(Redis::get("xflickr:pause:{$connectionKey}"));

        $this->cleanLimiterKeys($connectionKey);
    }

    /**
     * @return array{0: CrawlTarget, 1: FlickrClientFactory}
     */
    private function prepareSubjectTarget(TaskType $taskType, ?string $connectionKey = null): array
    {
        $connectionKey ??= FlickrNsid::fake();
        $subjectNsid = FlickrNsid::fake();

        $run = CrawlRun::query()->create([
            'connection_key' => $connectionKey,
            'crawl_type' => 'catalog',
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        Connection::query()->create([
            'connection_key' => $connectionKey,
            'app_profile' => 'default',
            'token_payload' => $this->sampleToken(),
        ]);

        $target = CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => $taskType,
            'subject_nsid' => $subjectNsid,
            'page' => 1,
            'status' => CrawlStatus::Pending,
        ]);

        return [$target, new FlickrClientFactory];
    }
}

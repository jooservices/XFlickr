<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Feature\Jobs;

use Illuminate\Support\Facades\Redis;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Fetchers\GalleriesListFetcher;
use Modules\Crawler\Fetchers\PeoplePhotosFetcher;
use Modules\Crawler\Fetchers\PhotosetsListFetcher;
use Modules\Crawler\Jobs\FetchGalleriesListJob;
use Modules\Crawler\Jobs\FetchPeoplePhotosJob;
use Modules\Crawler\Jobs\FetchPhotosetsListJob;
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
use Tests\Support\FlickrNsid;

final class InteractsWithXFlickrCrawlJobTest extends TestCase
{
    public function test_people_photos_job_fails_target_when_api_throws(): void
    {
        [$target] = $this->preparePeoplePhotosTarget();

        $this->app->instance(FlickrPermitAcquirer::class, new StubFlickrPermitAcquirer($this->grantedPermit()));

        (new FetchPeoplePhotosJob($target->id))->handle(
            new FlickrClientFactory(new ThrowingFlickrTransport('network down')),
            app(FlickrRequestLimiter::class),
            app(FlickrApiOutcomeClassifier::class),
            app(FlickrApiAuditService::class),
            app(FlickrSpiderService::class),
            app(PeoplePhotosFetcher::class),
        );

        $target->refresh();
        $this->assertSame(CrawlStatus::Failed, $target->status);
        $this->assertSame('network down', $target->failed_reason);
    }

    public function test_people_photos_job_fails_with_auth_token_invalid_on_empty_first_page(): void
    {
        [$target, $clients] = $this->preparePeoplePhotosTarget();

        $transport = FakeFlickrTransport::new()
            ->pushJson([
                'stat' => 'ok',
                'photos' => [
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

        $this->runPeoplePhotosJob($target->id, new FlickrClientFactory($transport));

        $target->refresh();
        $this->assertSame(CrawlStatus::Failed, $target->status);
        $this->assertSame('auth_token_invalid', $target->failed_reason);
    }

    public function test_photosets_list_job_fails_with_auth_token_invalid_on_empty_first_page(): void
    {
        [$target] = $this->prepareSubjectTarget(TaskType::PhotosetsList);

        $transport = FakeFlickrTransport::new()
            ->pushJson([
                'stat' => 'ok',
                'photosets' => [
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

        $this->runPhotosetsListJob($target->id, new FlickrClientFactory($transport));

        $target->refresh();
        $this->assertSame(CrawlStatus::Failed, $target->status);
        $this->assertSame('auth_token_invalid', $target->failed_reason);
    }

    public function test_galleries_list_job_fails_with_auth_token_invalid_on_empty_first_page(): void
    {
        [$target] = $this->prepareSubjectTarget(TaskType::GalleriesList);

        $transport = FakeFlickrTransport::new()
            ->pushJson([
                'stat' => 'ok',
                'galleries' => [
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

        $this->runGalleriesListJob($target->id, new FlickrClientFactory($transport));

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

        [$target] = $this->preparePeoplePhotosTarget($connectionKey);

        $transport = FakeFlickrTransport::new()->pushJson([
            'stat' => 'fail',
            'code' => 99,
            'message' => 'Rate limit exceeded',
        ]);

        $this->runPeoplePhotosJob($target->id, new FlickrClientFactory($transport), useRealPermitAcquirer: true);

        $target->refresh();
        $this->assertSame(CrawlStatus::Pending, $target->status);
        $this->assertGreaterThan(0, $target->retry_count);
        $this->assertNotNull(Redis::get("xflickr:pause:{$connectionKey}"));

        $this->cleanLimiterKeys($connectionKey);
    }

    /**
     * @return array{0: CrawlTarget, 1: FlickrClientFactory}
     */
    private function preparePeoplePhotosTarget(?string $connectionKey = null): array
    {
        $connectionKey ??= FlickrNsid::fake();
        $subjectNsid = FlickrNsid::fake();

        $run = CrawlRun::query()->create([
            'connection_key' => $connectionKey,
            'crawl_type' => 'photos',
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
            'task_type' => TaskType::PeoplePhotos,
            'subject_nsid' => $subjectNsid,
            'page' => 1,
            'status' => CrawlStatus::Pending,
        ]);

        return [$target, new FlickrClientFactory];
    }

    /**
     * @return array{0: CrawlTarget}
     */
    private function prepareSubjectTarget(TaskType $taskType): array
    {
        $connectionKey = FlickrNsid::fake();
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

        return [$target];
    }

    private function runPeoplePhotosJob(
        int $targetId,
        FlickrClientFactory $clients,
        bool $useRealPermitAcquirer = false,
    ): void {
        if ($useRealPermitAcquirer) {
            $this->app->instance(FlickrPermitAcquirer::class, new FlickrPermitAcquirer);
        } else {
            $this->app->instance(
                FlickrPermitAcquirer::class,
                new StubFlickrPermitAcquirer($this->grantedPermit()),
            );
        }

        (new FetchPeoplePhotosJob($targetId))->handle(
            $clients,
            app(FlickrRequestLimiter::class),
            app(FlickrApiOutcomeClassifier::class),
            app(FlickrApiAuditService::class),
            app(FlickrSpiderService::class),
            app(PeoplePhotosFetcher::class),
        );
    }

    private function runPhotosetsListJob(int $targetId, FlickrClientFactory $clients): void
    {
        $this->app->instance(
            FlickrPermitAcquirer::class,
            new StubFlickrPermitAcquirer($this->grantedPermit()),
        );

        (new FetchPhotosetsListJob($targetId))->handle(
            $clients,
            app(FlickrRequestLimiter::class),
            app(FlickrApiOutcomeClassifier::class),
            app(FlickrApiAuditService::class),
            app(FlickrSpiderService::class),
            app(PhotosetsListFetcher::class),
        );
    }

    private function runGalleriesListJob(int $targetId, FlickrClientFactory $clients): void
    {
        $this->app->instance(
            FlickrPermitAcquirer::class,
            new StubFlickrPermitAcquirer($this->grantedPermit()),
        );

        (new FetchGalleriesListJob($targetId))->handle(
            $clients,
            app(FlickrRequestLimiter::class),
            app(FlickrApiOutcomeClassifier::class),
            app(FlickrApiAuditService::class),
            app(FlickrSpiderService::class),
            app(GalleriesListFetcher::class),
        );
    }
}

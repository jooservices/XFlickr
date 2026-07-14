<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Feature\Jobs;

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
use Modules\Crawler\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FlickrNsid;

final class FetchCollectionPhotosJobsTest extends TestCase
{
    /**
     * @return iterable<string, array{0: TaskType, 1: array<string, mixed>}>
     */
    public static function subjectIdTaskTypeProvider(): iterable
    {
        yield 'photosets photos' => [
            TaskType::PhotosetsPhotos,
            [
                'stat' => 'ok',
                'photoset' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 500,
                    'total' => 1,
                    'photo' => [
                        [
                            'id' => (string) fake()->numerify('########'),
                            'owner' => FlickrNsid::fake(),
                            'title' => fake()->sentence(2),
                        ],
                    ],
                ],
            ],
        ];

        yield 'galleries photos' => [
            TaskType::GalleriesPhotos,
            [
                'stat' => 'ok',
                'photos' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 500,
                    'total' => 1,
                    'photo' => [
                        [
                            'id' => (string) fake()->numerify('########'),
                            'owner' => FlickrNsid::fake(),
                            'title' => fake()->sentence(2),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('subjectIdTaskTypeProvider')]
    public function test_job_completes_target_for_subject_id_task_types(TaskType $taskType, array $payload): void
    {
        [$target, $clients] = $this->prepareTarget(
            $taskType,
            subjectId: (string) fake()->numerify('########'),
            payload: $payload,
        );

        $this->app->instance(FlickrPermitAcquirer::class, new StubFlickrPermitAcquirer($this->grantedPermit()));

        (new FetchCrawlPageJob($target->id))->handle(
            $clients,
            app(FlickrRequestLimiter::class),
            app(FlickrApiOutcomeClassifier::class),
            app(FlickrApiAuditService::class),
            app(FlickrSpiderService::class),
        );

        $this->assertSame(CrawlStatus::Completed, $target->fresh()->status);
    }

    public function test_photosets_photos_job_aborts_when_subject_id_missing(): void
    {
        [$target, $clients] = $this->prepareTarget(
            TaskType::PhotosetsPhotos,
            subjectId: '',
            payload: ['stat' => 'ok', 'photoset' => ['photo' => []]],
        );

        $this->app->instance(FlickrPermitAcquirer::class, new StubFlickrPermitAcquirer($this->grantedPermit()));

        (new FetchCrawlPageJob($target->id))->handle(
            $clients,
            app(FlickrRequestLimiter::class),
            app(FlickrApiOutcomeClassifier::class),
            app(FlickrApiAuditService::class),
            app(FlickrSpiderService::class),
        );

        $this->assertSame(CrawlStatus::Processing, $target->fresh()->status);
        $this->assertDatabaseMissing('xflickr_api_logs', [
            'connection_key' => $target->crawlRun?->connection_key ?? $target->fresh()->crawlRun?->connection_key,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: CrawlTarget, 1: FlickrClientFactory}
     */
    private function prepareTarget(TaskType $taskType, ?string $subjectId, array $payload): array
    {
        $connectionKey = FlickrNsid::fake();
        $subjectNsid = FlickrNsid::fake();

        $run = CrawlRun::query()->create([
            'connection_key' => $connectionKey,
            'crawl_type' => 'photosets',
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
            'subject_id' => $subjectId,
            'page' => 1,
            'status' => CrawlStatus::Pending,
        ]);

        $transport = FakeFlickrTransport::new()->pushJson($payload);

        return [$target, new FlickrClientFactory($transport)];
    }
}

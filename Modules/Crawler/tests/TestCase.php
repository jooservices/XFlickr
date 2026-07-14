<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests;

use Illuminate\Support\Facades\Redis;
use JOOservices\LaravelConfig\Contracts\ConfigStore;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Contracts\PageFetcherContract;
use Modules\Crawler\DTO\FlickrPermit;
use Modules\Crawler\Fetchers\ContactsFetcher;
use Modules\Crawler\Jobs\FetchContactsPageJob;
use Modules\Crawler\Services\FlickrApiAuditService;
use Modules\Crawler\Services\FlickrApiOutcomeClassifier;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Services\FlickrPermitAcquirer;
use Modules\Crawler\Services\FlickrRequestLimiter;
use Modules\Crawler\Services\FlickrSpiderService;
use Modules\Crawler\Tests\Support\StubFlickrPermitAcquirer;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase as HostTestCase;

abstract class TestCase extends HostTestCase
{
    use IgnoresAuthentication;
    use SafeRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'xflickr-crawler.default_app_profile' => 'default',
            'xflickr-crawler.throttle.max_requests_per_hour' => 10,
            'xflickr-crawler.throttle.min_gap_ms' => 0,
            'xflickr-crawler.crawl.dispatch_limit' => 5,
        ]);

        if (! app()->bound('config-store') && ! app()->bound(ConfigStore::class)) {
            $this->markTestSkipped('Host laravel-config store is required for crawler tests.');
        }

        foreach ([
            'xflickr.global_pause',
            'xflickr.default_app_profile',
            'xflickr_app.main',
            'xflickr_app.incomplete',
            'xflickr_crawl.safe_search',
            'xflickr_crawl.privacy_filter',
            'xflickr_crawl.people_photos_safe_search',
        ] as $path) {
            if (RuntimeConfig::has($path)) {
                RuntimeConfig::forget($path);
            }
        }

        RuntimeConfig::set('xflickr_app.default', [
            'apiKey' => 'test-api-key',
            'apiSecret' => 'test-api-secret',
        ], 'json');
        RuntimeConfig::refresh();
    }

    protected function requiresRedis(): void
    {
        try {
            Redis::connection()->ping();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Redis is required for this test: '.$exception->getMessage());
        }
    }

    protected function sampleToken(): string
    {
        return json_encode([
            'oauthToken' => 'token',
            'oauthTokenSecret' => 'secret',
            'userNsid' => '12037949629@N01',
        ], JSON_THROW_ON_ERROR);
    }

    protected function grantedPermit(): FlickrPermit
    {
        return new FlickrPermit(true, 0);
    }

    protected function deniedPermit(int $retryAfterSeconds = 60): FlickrPermit
    {
        return new FlickrPermit(false, $retryAfterSeconds);
    }

    protected function cleanLimiterKeys(string ...$connectionKeys): void
    {
        foreach ($connectionKeys as $connectionKey) {
            Redis::del(
                "xflickr:req:{$connectionKey}:window",
                "xflickr:req:{$connectionKey}:last",
                "xflickr:pause:{$connectionKey}",
            );
        }
    }

    protected function runContactsPageJob(
        int $targetId,
        ?FlickrPermit $permitOverride = null,
        ?FlickrClientFactory $clients = null,
        bool $useRealPermitAcquirer = false,
        ?PageFetcherContract $fetcher = null,
    ): void {
        if ($useRealPermitAcquirer) {
            $this->app->instance(FlickrPermitAcquirer::class, new FlickrPermitAcquirer);
        } else {
            $this->app->instance(
                FlickrPermitAcquirer::class,
                new StubFlickrPermitAcquirer($permitOverride ?? $this->grantedPermit()),
            );
        }

        (new FetchContactsPageJob($targetId))->handle(
            $clients ?? app(FlickrClientFactory::class),
            app(FlickrRequestLimiter::class),
            app(FlickrApiOutcomeClassifier::class),
            app(FlickrApiAuditService::class),
            app(FlickrSpiderService::class),
            $fetcher ?? app(ContactsFetcher::class),
        );
    }
}

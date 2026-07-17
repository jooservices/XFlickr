<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Feature\Console;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use JOOservices\LaravelLogging\Jobs\StoreActivityLogJob;
use Modules\Crawler\Services\FlickrPermitAcquirer;
use Modules\Crawler\Services\FlickrRequestLimiter;
use Modules\Crawler\Tests\TestCase;

final class RateLimitTest extends TestCase
{
    private string $rateKey = 'rate-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->requiresRedis();

        $this->rateKey = 'rate-test-'.uniqid();

        Redis::del(
            "xflickr:req:{$this->rateKey}:window",
            "xflickr:req:{$this->rateKey}:last",
            "xflickr:pause:{$this->rateKey}",
        );

        config()->set('xflickr-crawler.throttle.max_requests_per_hour', 2);
        config()->set('xflickr-crawler.throttle.min_gap_ms', 0);
        config()->set('xflickr-crawler.throttle.rate_limit_backoff_seconds', 3600);
    }

    public function test_hourly_window_blocks_after_max_requests(): void
    {
        $limiter = app(FlickrRequestLimiter::class);
        $key = $this->rateKey;

        $this->assertTrue($limiter->acquire($key)->acquired);
        $this->assertTrue($limiter->acquire($key)->acquired);
        $this->assertFalse($limiter->acquire($key)->acquired);

        $state = $limiter->state($key)->toArray();
        $this->assertSame(2, $state['requests_used']);
        $this->assertSame(0, $state['requests_remaining']);
    }

    public function test_global_cooldown_sets_pause_key_with_ttl(): void
    {
        Log::spy();
        Queue::fake();

        $limiter = app(FlickrRequestLimiter::class);
        $key = 'cooldown-test-'.uniqid();

        $until = $limiter->triggerGlobalCooldown($key);

        $this->assertTrue($until->isFuture());
        $this->assertFalse($limiter->acquire($key)->acquired);

        $ttl = (int) Redis::ttl("xflickr:pause:{$key}");
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(3600, $ttl);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message): bool => $message === 'Crawler global cooldown triggered.');
        Queue::assertPushedOn('logging', StoreActivityLogJob::class, fn (StoreActivityLogJob $job): bool => $job->data->action === 'crawler.cooldown.triggered');

        Redis::del("xflickr:pause:{$key}");
    }

    public function test_global_cooldown_reports_remaining_seconds(): void
    {
        $limiter = app(FlickrRequestLimiter::class);
        $key = 'cooldown-remaining-'.uniqid();

        $limiter->triggerGlobalCooldown($key);

        $permit = $limiter->acquire($key);
        $this->assertFalse($permit->acquired);
        $this->assertGreaterThan(100, $permit->retryAfterSeconds);

        $state = $limiter->state($key);
        $this->assertGreaterThan(100, $state->globalPauseSecondsRemaining);

        Redis::del("xflickr:pause:{$key}");
    }

    public function test_permit_acquirer_does_not_sleep_on_denied_permit(): void
    {
        $limiter = app(FlickrRequestLimiter::class);
        $acquirer = app(FlickrPermitAcquirer::class);
        $key = 'acquirer-test-'.uniqid();

        $limiter->triggerGlobalCooldown($key);

        $started = microtime(true);
        $permit = $acquirer->acquire($limiter, $key);
        $elapsed = microtime(true) - $started;

        $this->assertFalse($permit->acquired);
        $this->assertLessThan(1.0, $elapsed);

        Redis::del("xflickr:pause:{$key}");
    }
}

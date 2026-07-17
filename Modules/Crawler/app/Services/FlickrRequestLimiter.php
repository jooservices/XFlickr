<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Modules\Crawler\DTO\FlickrPermit;
use Modules\Crawler\DTO\LimiterStateDto;
use Modules\Crawler\Support\XFlickrConfig;

final class FlickrRequestLimiter
{
    public function __construct(
        private readonly CrawlerObservability $observability,
    ) {}

    public function acquire(string $connectionKey): FlickrPermit
    {
        if (XFlickrConfig::globalPause()) {
            return new FlickrPermit(false, 60);
        }

        $pauseUntil = $this->globalPauseUntil($connectionKey);
        if ($pauseUntil !== null && $pauseUntil->isFuture()) {
            return new FlickrPermit(false, $this->secondsUntil($pauseUntil));
        }

        $gap = $this->claimMinGap($connectionKey);
        if (! $gap['acquired']) {
            return new FlickrPermit(false, max(1, (int) ceil($gap['retry_after_ms'] / 1000)));
        }

        $hourly = $this->claimHourlyWindow($connectionKey);
        if (! $hourly['acquired']) {
            return new FlickrPermit(false, max(1, (int) ceil($hourly['retry_after_ms'] / 1000)));
        }

        return new FlickrPermit(true, 0, CarbonImmutable::now());
    }

    public function triggerGlobalCooldown(string $connectionKey): CarbonImmutable
    {
        $seconds = XFlickrConfig::throttle('rate_limit_backoff_seconds', 3600);
        $until = CarbonImmutable::now()->addSeconds($seconds);
        Redis::setex($this->pauseKey($connectionKey), $seconds, (string) $until->getTimestamp());

        $this->observability->cooldownTriggered($seconds, $connectionKey);

        return $until;
    }

    public function state(string $connectionKey): LimiterStateDto
    {
        $windowSeconds = XFlickrConfig::throttle('window_seconds', 3600);
        $maxRequests = XFlickrConfig::maxRequestsPerHour();
        $nowMs = (int) floor(microtime(true) * 1000);
        $windowStart = $nowMs - ($windowSeconds * 1000);

        Redis::zremrangebyscore($this->windowKey($connectionKey), '0', (string) $windowStart);
        $used = (int) Redis::zcard($this->windowKey($connectionKey));
        $pauseUntil = $this->globalPauseUntil($connectionKey);

        return new LimiterStateDto(
            connectionKey: $connectionKey,
            maxRequestsPerHour: $maxRequests,
            requestsUsed: $used,
            requestsRemaining: max(0, $maxRequests - $used),
            minGapMs: XFlickrConfig::throttle('min_gap_ms', 333),
            globalPause: XFlickrConfig::globalPause(),
            globalPauseUntil: $pauseUntil?->toISOString(),
            globalPauseSecondsRemaining: $pauseUntil?->isFuture()
                ? max(0, (int) now()->diffInSeconds($pauseUntil, false))
                : 0,
        );
    }

    private function pauseKey(string $connectionKey): string
    {
        return "xflickr:pause:{$connectionKey}";
    }

    private function windowKey(string $connectionKey): string
    {
        return "xflickr:req:{$connectionKey}:window";
    }

    private function lastRequestKey(string $connectionKey): string
    {
        return "xflickr:req:{$connectionKey}:last";
    }

    private function globalPauseUntil(string $connectionKey): ?CarbonImmutable
    {
        $value = Redis::get($this->pauseKey($connectionKey));
        if (! is_numeric($value)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $value);
    }

    private function secondsUntil(CarbonImmutable $until): int
    {
        return max(1, (int) now()->diffInSeconds($until, false));
    }

    /**
     * @return array{acquired: bool, retry_after_ms: int}
     */
    private function claimHourlyWindow(string $connectionKey): array
    {
        $windowSeconds = XFlickrConfig::throttle('window_seconds', 3600);
        $maxRequests = XFlickrConfig::maxRequestsPerHour();
        $nowMs = (int) floor(microtime(true) * 1000);
        $windowStart = $nowMs - ($windowSeconds * 1000);
        $member = Str::uuid()->toString();

        $result = Redis::connection()->command('eval', [
            $this->hourlyWindowScript(),
            [
                $this->windowKey($connectionKey),
                (string) $nowMs,
                (string) $windowStart,
                (string) $maxRequests,
                (string) ($windowSeconds + 60),
                $member,
            ],
            1,
        ]);

        if (! is_array($result)) {
            return ['acquired' => true, 'retry_after_ms' => 0];
        }

        $acquired = $result[0] ?? 1;
        $retryAfterMs = $result[1] ?? 0;

        return [
            'acquired' => (is_numeric($acquired) ? (int) $acquired : 1) === 1,
            'retry_after_ms' => max(0, is_numeric($retryAfterMs) ? (int) $retryAfterMs : 0),
        ];
    }

    /**
     * @return array{acquired: bool, retry_after_ms: int}
     */
    private function claimMinGap(string $connectionKey): array
    {
        $gapMs = XFlickrConfig::throttle('min_gap_ms', 333);
        $gapSeconds = max(0.0, $gapMs / 1000);
        $now = microtime(true);

        $result = Redis::connection()->command('eval', [
            $this->claimScript(),
            [
                $this->lastRequestKey($connectionKey),
                (string) $now,
                (string) $gapSeconds,
                '3600',
            ],
            1,
        ]);

        if (! is_array($result)) {
            return ['acquired' => true, 'retry_after_ms' => 0];
        }

        $acquired = $result[0] ?? 1;
        $retryAfterMs = $result[1] ?? 0;

        return [
            'acquired' => (is_numeric($acquired) ? (int) $acquired : 1) === 1,
            'retry_after_ms' => max(0, is_numeric($retryAfterMs) ? (int) $retryAfterMs : 0),
        ];
    }

    private function hourlyWindowScript(): string
    {
        return <<<'LUA'
local windowKey = KEYS[1]
local nowMs = tonumber(ARGV[1])
local windowStart = tonumber(ARGV[2])
local maxRequests = tonumber(ARGV[3])
local ttl = tonumber(ARGV[4])
local member = ARGV[5]

redis.call('ZREMRANGEBYSCORE', windowKey, '0', tostring(windowStart))
local count = redis.call('ZCARD', windowKey)

if count >= maxRequests then
    local oldest = redis.call('ZRANGE', windowKey, 0, 0, 'WITHSCORES')
    local oldestScore = 0
    if oldest[2] ~= nil then
        oldestScore = tonumber(oldest[2])
    end
    local windowSeconds = math.floor((nowMs - windowStart) / 1000)
    local retryAfterMs = math.max(0, (oldestScore + (windowSeconds * 1000)) - nowMs)
    return {0, retryAfterMs}
end

redis.call('ZADD', windowKey, nowMs, member)
redis.call('EXPIRE', windowKey, ttl)

return {1, 0}
LUA;
    }

    private function claimScript(): string
    {
        return <<<'LUA'
local now = tonumber(ARGV[1])
local gap = tonumber(ARGV[2])
local ttl = tonumber(ARGV[3])

if gap <= 0 then
    redis.call('SETEX', KEYS[1], ttl, tostring(now))
    return {1, 0}
end

local last = tonumber(redis.call('GET', KEYS[1]) or '')
local availableAt = now

if last ~= nil then
    availableAt = math.max(availableAt, last + gap)
end

if availableAt <= now then
    redis.call('SETEX', KEYS[1], ttl, tostring(now))
    return {1, 0}
end

return {0, math.ceil((availableAt - now) * 1000)}
LUA;
    }
}

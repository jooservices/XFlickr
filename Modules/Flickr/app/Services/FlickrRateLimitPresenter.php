<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Redis;
use Modules\Crawler\Facades\FlickrService;
use Modules\Crawler\Support\XFlickrConfig;

final class FlickrRateLimitPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(string $connectionKey): array
    {
        $state = FlickrService::limiterState($connectionKey);
        $windowSeconds = XFlickrConfig::throttle('window_seconds', 3600);
        $windowReset = $this->windowResetMeta($connectionKey, $windowSeconds);

        return [
            'requests_used' => $state['requests_used'],
            'max_requests_per_hour' => $state['max_requests_per_hour'],
            'requests_remaining' => $state['requests_remaining'],
            'window_seconds' => $windowSeconds,
            'window_reset_at' => $windowReset['reset_at'],
            'window_seconds_remaining' => $windowReset['seconds_remaining'],
            'global_pause' => $state['global_pause'],
            'cooldown_until' => $state['global_pause_until'],
            'cooldown_seconds_remaining' => $state['global_pause_seconds_remaining'],
        ];
    }

    /**
     * @return array{reset_at: string|null, seconds_remaining: int}
     */
    private function windowResetMeta(string $connectionKey, int $windowSeconds): array
    {
        $nowMs = (int) floor(microtime(true) * 1000);
        $windowStart = $nowMs - ($windowSeconds * 1000);
        $windowKey = "xflickr:req:{$connectionKey}:window";

        Redis::zremrangebyscore($windowKey, '0', (string) $windowStart);
        $count = (int) Redis::zcard($windowKey);

        if ($count === 0) {
            return ['reset_at' => null, 'seconds_remaining' => 0];
        }

        $oldest = Redis::zrange($windowKey, 0, 0, ['WITHSCORES' => true]);
        $oldestScore = 0;

        if (is_array($oldest)) {
            foreach ($oldest as $score) {
                if (is_numeric($score)) {
                    $oldestScore = (int) $score;
                    break;
                }
            }
        }

        if ($oldestScore === 0) {
            return ['reset_at' => null, 'seconds_remaining' => 0];
        }

        $resetAtMs = $oldestScore + ($windowSeconds * 1000);
        $secondsRemaining = max(0, (int) ceil(($resetAtMs - $nowMs) / 1000));
        $resetAt = CarbonImmutable::createFromTimestampMs($resetAtMs)->toIso8601String();

        return [
            'reset_at' => $resetAt,
            'seconds_remaining' => $secondsRemaining,
        ];
    }
}

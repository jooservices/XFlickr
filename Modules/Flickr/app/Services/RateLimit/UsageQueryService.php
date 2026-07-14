<?php

declare(strict_types=1);

namespace Modules\Flickr\Services\RateLimit;

use App\Repositories\Crawler\ApiLogQueryRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Modules\Crawler\Facades\FlickrService;

final class UsageQueryService
{
    private const MAX_HOURS = 48;

    public function __construct(
        private readonly ApiLogQueryRepository $apiLogs,
        private readonly Presenter $rateLimit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function usage(string $connectionKey, int $hours): array
    {
        $this->assertConnectionExists($connectionKey);

        $hours = min(max($hours, 1), self::MAX_HOURS);

        return Cache::remember(
            "xflickr:rate-limit:usage:{$connectionKey}:{$hours}",
            5,
            fn (): array => $this->buildUsage($connectionKey, $hours),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUsage(string $connectionKey, int $hours): array
    {
        $now = CarbonImmutable::now();
        $currentHourStart = $now->startOfHour();
        $seriesStart = $currentHourStart->subHours($hours - 1);

        $countsByHour = [];
        foreach ($this->apiLogs->hourlyCountsForConnection($connectionKey, $seriesStart) as $row) {
            $countsByHour[$row['hour_start']] = $row['requests'];
        }

        $buckets = [];
        for ($offset = 0; $offset < $hours; $offset++) {
            $hourStart = $seriesStart->addHours($offset);
            $hourKey = $hourStart->format('Y-m-d H:00:00');

            $buckets[] = [
                'hour_start' => $hourStart->toIso8601String(),
                'requests' => $countsByHour[$hourKey] ?? 0,
                'is_current' => $hourStart->equalTo($currentHourStart),
            ];
        }

        $rateLimit = $this->rateLimit->present($connectionKey);

        return [
            'connection_key' => $connectionKey,
            'hours' => $hours,
            'generated_at' => $now->toIso8601String(),
            'max_requests_per_hour' => $rateLimit['max_requests_per_hour'],
            'buckets' => $buckets,
            'rate_limit' => $rateLimit,
        ];
    }

    private function assertConnectionExists(string $connectionKey): void
    {
        $exists = FlickrService::connections()->list()
            ->contains(fn ($connection): bool => (string) $connection->connection_key === $connectionKey);

        if (! $exists) {
            throw ValidationException::withMessages([
                'connection_key' => ['The selected connection key is invalid.'],
            ]);
        }
    }
}

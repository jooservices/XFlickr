<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JOOservices\XFlickrCrawler\Models\ApiLog;

final class ApiLogQueryRepository
{
    public function paginateForConnection(string $connectionKey, int $perPage, int $page): LengthAwarePaginator
    {
        return ApiLog::query()
            ->where('connection_key', $connectionKey)
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return Collection<int, object{hour_start: string, requests: int}>
     */
    public function hourlyCountsForConnection(string $connectionKey, CarbonImmutable $since): Collection
    {
        $hourBucket = $this->hourBucketExpression();

        return ApiLog::query()
            ->selectRaw("{$hourBucket} as hour_start, COUNT(*) as requests")
            ->where('connection_key', $connectionKey)
            ->where('created_at', '>=', $since)
            ->groupBy('hour_start')
            ->orderBy('hour_start')
            ->get()
            ->map(static fn (object $row): object => (object) [
                'hour_start' => (string) $row->hour_start,
                'requests' => (int) $row->requests,
            ]);
    }

    private function hourBucketExpression(): string
    {
        $driver = DB::connection(ApiLog::query()->getModel()->getConnectionName())->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d %H:00:00', created_at)",
            default => "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')",
        };
    }
}

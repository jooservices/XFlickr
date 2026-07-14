<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Crawler\Models\ApiLog;

final class ApiLogQueryRepository
{
    /**
     * @return LengthAwarePaginator<int, ApiLog>
     */
    public function paginateForConnection(string $connectionKey, int $perPage, int $page): LengthAwarePaginator
    {
        return ApiLog::query()
            ->where('connection_key', $connectionKey)
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return Collection<int, array{hour_start: string, requests: int}>
     */
    public function hourlyCountsForConnection(string $connectionKey, CarbonImmutable $since): Collection
    {
        $hourBucket = $this->hourBucketExpression();

        /** @var Collection<int, object{hour_start: mixed, requests: mixed}> $rows */
        $rows = ApiLog::query()
            ->selectRaw("{$hourBucket} as hour_start, COUNT(*) as requests")
            ->where('connection_key', $connectionKey)
            ->where('created_at', '>=', $since)
            ->groupBy('hour_start')
            ->orderBy('hour_start')
            ->toBase()
            ->get();

        /** @var list<array{hour_start: string, requests: int}> $counts */
        $counts = $rows
            ->map(static fn (object $row): array => [
                'hour_start' => (string) $row->hour_start,
                'requests' => (int) $row->requests,
            ])
            ->values()
            ->all();

        return collect($counts);
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

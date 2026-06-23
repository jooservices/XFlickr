<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
}

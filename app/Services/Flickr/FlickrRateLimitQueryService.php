<?php

declare(strict_types=1);

namespace App\Services\Flickr;

use App\Support\Flickr\ConnectionPresenter;
use JOOservices\XFlickrCrawler\Facades\FlickrService;

final class FlickrRateLimitQueryService
{
    public function __construct(
        private readonly FlickrRateLimitPresenter $rateLimit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $connections = FlickrService::connections()->list();
        $active = FlickrService::connections()->active();

        $accounts = [];
        foreach ($connections as $connection) {
            $key = (string) $connection->connection_key;

            $accounts[] = [
                'account' => ConnectionPresenter::toArray($connection),
                'rate_limit' => $this->rateLimit->present($key),
            ];
        }

        return [
            'generated_at' => now()->toISOString(),
            'active_connection_key' => $active?->connection_key,
            'accounts' => $accounts,
        ];
    }
}

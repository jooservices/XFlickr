<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use Illuminate\Support\Collection;
use Modules\Flickr\Services\FlickrOAuthService;
use Modules\Spider\Support\SpiderRuntimeConfig;

final class CrawlOperationsService
{
    public function __construct(
        private readonly FlickrOAuthService $oauth,
        private readonly SpiderRuntimeConfig $spiderConfig,
    ) {}

    /**
     * @return array{accounts: Collection<int, array<string, mixed>>, spiderEnabled: bool}
     */
    public function pageProps(): array
    {
        return [
            'accounts' => $this->oauth->listAccounts(),
            'spiderEnabled' => $this->spiderConfig->enabled(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use Illuminate\Support\Collection;
use Modules\Flickr\Services\FlickrOAuthService;

final class CrawlOperationsService
{
    public function __construct(
        private readonly FlickrOAuthService $oauth,
    ) {}

    /**
     * @return array{accounts: Collection<int, array<string, mixed>>}
     */
    public function pageProps(): array
    {
        return [
            'accounts' => $this->oauth->listAccounts(),
        ];
    }
}

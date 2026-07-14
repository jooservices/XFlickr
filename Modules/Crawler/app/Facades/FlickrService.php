<?php

declare(strict_types=1);

namespace Modules\Crawler\Facades;

use Illuminate\Support\Facades\Facade;
use Modules\Crawler\FlickrConnection;
use Modules\Crawler\FlickrCrawlerManager;

/**
 * @method static FlickrConnection connection(string $connectionKey, string $token, ?string $appProfile = null)
 * @method static array<string, mixed> limiterState(string $connectionKey)
 * @method static \Modules\Crawler\Services\CrawlerCatalog catalog()
 * @method static \Modules\Crawler\Services\CrawlerRuns runs()
 * @method static \Modules\Crawler\Services\ConnectionRegistryService connections()
 *
 * @see FlickrCrawlerManager
 */
final class FlickrService extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FlickrCrawlerManager::class;
    }
}

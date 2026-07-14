<?php

declare(strict_types=1);

namespace Modules\Crawler\Support;

use Illuminate\Contracts\Foundation\Application;
use Modules\Crawler\Repositories\ApiLogRepository;
use Modules\Crawler\Repositories\ConnectionContactRepository;
use Modules\Crawler\Repositories\ConnectionRepository;
use Modules\Crawler\Repositories\ContactRepository;
use Modules\Crawler\Repositories\CrawlRunRepository;
use Modules\Crawler\Repositories\CrawlTargetRepository;
use Modules\Crawler\Repositories\FavoriteRepository;
use Modules\Crawler\Repositories\GalleryRepository;
use Modules\Crawler\Repositories\PhotoRepository;
use Modules\Crawler\Repositories\PhotosetRepository;
use Modules\Crawler\Repositories\PivotRepository;
use Modules\Crawler\Repositories\SubjectContactRepository;

final class RepositoryRegistrar
{
    /**
     * @param  Application  $app
     */
    public static function register(object $app): void
    {
        foreach ([
            ApiLogRepository::class,
            ContactRepository::class,
            ConnectionRepository::class,
            ConnectionContactRepository::class,
            CrawlRunRepository::class,
            CrawlTargetRepository::class,
            FavoriteRepository::class,
            PhotoRepository::class,
            PhotosetRepository::class,
            GalleryRepository::class,
            PivotRepository::class,
            SubjectContactRepository::class,
        ] as $repository) {
            $app->singleton($repository);
        }
    }
}

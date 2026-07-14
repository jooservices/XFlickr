<?php

declare(strict_types=1);

namespace Modules\Crawler\Providers;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use Modules\Crawler\Console\DispatchCrawlTargetsCommand;
use Modules\Crawler\Console\DoctorCommand;
use Modules\Crawler\Console\PruneCrawlDataCommand;
use Modules\Crawler\Contracts\PageFetcherContract;
use Modules\Crawler\Facades\FlickrService;
use Modules\Crawler\Fetchers\ContactsFetcher;
use Modules\Crawler\Fetchers\SubjectContactsFetcher;
use Modules\Crawler\FlickrCrawlerManager;
use Modules\Crawler\Jobs\CrawlTargetJobFactory;
use Modules\Crawler\Jobs\FetchContactsPageJob;
use Modules\Crawler\Jobs\FetchSubjectContactsPageJob;
use Modules\Crawler\Services\ConnectionRegistryService;
use Modules\Crawler\Services\CrawlerCatalog;
use Modules\Crawler\Services\CrawlerRuns;
use Modules\Crawler\Services\CrawlingService;
use Modules\Crawler\Services\FlickrApiAuditService;
use Modules\Crawler\Services\FlickrApiOutcomeClassifier;
use Modules\Crawler\Services\FlickrCatalogService;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Services\FlickrFavoritesPersistence;
use Modules\Crawler\Services\FlickrPermitAcquirer;
use Modules\Crawler\Services\FlickrRequestLimiter;
use Modules\Crawler\Services\FlickrSpiderService;
use Modules\Crawler\Support\RepositoryRegistrar;

final class CrawlerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/xflickr-crawler.php', 'xflickr-crawler');

        $this->app->singleton(FlickrCrawlerManager::class);
        $this->app->singleton(FlickrPermitAcquirer::class);
        $this->app->singleton(FlickrRequestLimiter::class);
        $this->app->singleton(FlickrApiOutcomeClassifier::class);
        $this->app->singleton(FlickrApiAuditService::class);
        $this->app->singleton(FlickrClientFactory::class);
        $this->app->singleton(FlickrSpiderService::class);
        $this->app->singleton(CrawlTargetJobFactory::class);
        $this->app->singleton(CrawlingService::class);
        $this->app->singleton(CrawlerCatalog::class);
        $this->app->singleton(ConnectionRegistryService::class);
        $this->app->singleton(CrawlerRuns::class);
        $this->app->singleton(FlickrCatalogService::class);
        $this->app->singleton(FlickrFavoritesPersistence::class);

        $this->app->when(FetchContactsPageJob::class)
            ->needs(PageFetcherContract::class)
            ->give(ContactsFetcher::class);

        $this->app->when(FetchSubjectContactsPageJob::class)
            ->needs(PageFetcherContract::class)
            ->give(SubjectContactsFetcher::class);

        RepositoryRegistrar::register($this->app);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->publishes([
            __DIR__.'/../../config/xflickr-crawler.php' => config_path('xflickr-crawler.php'),
        ], 'xflickr-crawler-config');

        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'xflickr-crawler-migrations');

        AliasLoader::getInstance()->alias('FlickrService', FlickrService::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                DispatchCrawlTargetsCommand::class,
                DoctorCommand::class,
                PruneCrawlDataCommand::class,
            ]);
        }
    }
}

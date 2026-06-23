<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\ConfigEntryRepository;
use App\Repositories\Crawler\ApiLogQueryRepository;
use App\Repositories\Crawler\CatalogQueryRepository;
use App\Repositories\Crawler\ConnectionContactQueryRepository;
use App\Repositories\Crawler\ConnectionQueryRepository;
use App\Repositories\Crawler\ContactQueryRepository;
use App\Repositories\Crawler\CrawlRunQueryRepository;
use App\Repositories\Crawler\CrawlTargetQueryRepository;
use App\Repositories\Crawler\PhotoQueryRepository;
use App\Repositories\StorageAccountRepository;
use App\Repositories\StorageRemoteAlbumRepository;
use App\Repositories\StorageRemoteItemRepository;
use App\Repositories\StorageRemoteSyncStateRepository;
use App\Repositories\StorageUploadRepository;
use App\Repositories\StoredFileRepository;
use App\Repositories\TransferBatchRepository;
use App\Repositories\TransferItemRepository;
use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ([
            StoredFileRepository::class,
            StorageUploadRepository::class,
            TransferBatchRepository::class,
            TransferItemRepository::class,
            StorageAccountRepository::class,
            StorageRemoteAlbumRepository::class,
            StorageRemoteItemRepository::class,
            StorageRemoteSyncStateRepository::class,
            ConfigEntryRepository::class,
            PhotoQueryRepository::class,
            ContactQueryRepository::class,
            ConnectionContactQueryRepository::class,
            ConnectionQueryRepository::class,
            CatalogQueryRepository::class,
            CrawlRunQueryRepository::class,
            CrawlTargetQueryRepository::class,
            ApiLogQueryRepository::class,
        ] as $repository) {
            $this->app->singleton($repository);
        }
    }
}

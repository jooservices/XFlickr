<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Crawler\ApiLogQueryRepository;
use App\Repositories\Crawler\CatalogQueryRepository;
use App\Repositories\Crawler\ConnectionContactQueryRepository;
use App\Repositories\Crawler\ConnectionQueryRepository;
use App\Repositories\Crawler\ContactQueryRepository;
use App\Repositories\Crawler\CrawlRunQueryRepository;
use App\Repositories\Crawler\CrawlTargetQueryRepository;
use App\Repositories\Crawler\PhotoQueryRepository;
use App\Repositories\Crawler\SubjectContactQueryRepository;
use Illuminate\Support\ServiceProvider;
use Modules\Auth\Repositories\PasswordResetTokenRepository;
use Modules\Auth\Repositories\UserRepository;
use Modules\Contacts\Repositories\ContactAnnotationRepository;
use Modules\Contacts\Repositories\ContactFullPassFrontierRepository;
use Modules\Contacts\Repositories\ContactFullPassRunRepository;
use Modules\Spider\Repositories\SpiderFrontierRepository;
use Modules\Spider\Repositories\SpiderRunRepository;
use Modules\Storage\Repositories\StorageAccountRepository;
use Modules\Storage\Repositories\StorageRemoteAlbumRepository;
use Modules\Storage\Repositories\StorageRemoteItemRepository;
use Modules\Storage\Repositories\StorageRemoteSyncStateRepository;
use Modules\Storage\Repositories\StorageUploadRepository;
use Modules\Storage\Repositories\StoredFileRepository;
use Modules\Storage\Repositories\TransferBatchRepository;
use Modules\Storage\Repositories\TransferItemRepository;

final class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ([
            UserRepository::class,
            PasswordResetTokenRepository::class,
            StoredFileRepository::class,
            StorageUploadRepository::class,
            TransferBatchRepository::class,
            TransferItemRepository::class,
            StorageAccountRepository::class,
            StorageRemoteAlbumRepository::class,
            StorageRemoteItemRepository::class,
            StorageRemoteSyncStateRepository::class,
            ContactAnnotationRepository::class,
            SpiderRunRepository::class,
            SpiderFrontierRepository::class,
            ContactFullPassRunRepository::class,
            ContactFullPassFrontierRepository::class,
            PhotoQueryRepository::class,
            ContactQueryRepository::class,
            ConnectionContactQueryRepository::class,
            SubjectContactQueryRepository::class,
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

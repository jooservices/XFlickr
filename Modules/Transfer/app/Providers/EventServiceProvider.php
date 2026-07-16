<?php

declare(strict_types=1);

namespace Modules\Transfer\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Storage\Events\StorageRemoteItemsRemoved;
use Modules\Transfer\Listeners\DeleteStorageUploadRecords;

final class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, list<class-string>> */
    protected $listen = [
        StorageRemoteItemsRemoved::class => [
            DeleteStorageUploadRecords::class,
        ],
    ];
}

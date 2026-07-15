<?php

declare(strict_types=1);

namespace Modules\Storage\Providers;

use Modules\Storage\Console\Commands\VerifyGooglePhotosConnectionCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class StorageServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Storage';

    protected string $nameLower = 'storage';

    /** @var string[] */
    protected array $commands = [
        VerifyGooglePhotosConnectionCommand::class,
    ];

    /** @var string[] */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}

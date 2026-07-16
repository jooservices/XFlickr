<?php

declare(strict_types=1);

namespace Modules\Storage\Providers;

use Modules\Storage\Console\Commands\VerifyConnectionsCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class StorageServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Storage';

    protected string $nameLower = 'storage';

    /** @var string[] */
    protected array $commands = [
        VerifyConnectionsCommand::class,
    ];

    /** @var string[] */
    protected array $providers = [
        RouteServiceProvider::class,
    ];
}

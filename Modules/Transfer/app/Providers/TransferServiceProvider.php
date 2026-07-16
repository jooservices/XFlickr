<?php

declare(strict_types=1);

namespace Modules\Transfer\Providers;

use Modules\Transfer\Console\Commands\ScanStorageIntegrityCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

final class TransferServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Transfer';

    protected string $nameLower = 'transfer';

    /** @var string[] */
    protected array $commands = [
        ScanStorageIntegrityCommand::class,
    ];

    /** @var string[] */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}

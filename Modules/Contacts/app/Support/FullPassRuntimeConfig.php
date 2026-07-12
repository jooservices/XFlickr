<?php

declare(strict_types=1);

namespace Modules\Contacts\Support;

use App\Support\CuratedConfigCatalog;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Spider\Support\SpiderRuntimeConfig;

final class FullPassRuntimeConfig
{
    public function __construct(
        private readonly CuratedConfigCatalog $catalog,
        private readonly SpiderRuntimeConfig $spiderConfig,
    ) {}

    public function maxDepth(): int
    {
        return (int) $this->effectiveValue('full_pass.max_depth');
    }

    public function maxContactsPerBatch(): int
    {
        return $this->spiderConfig->maxNewContactsPerRun();
    }

    public function maxContactsTotal(): int
    {
        return $this->spiderConfig->maxContactsTotal();
    }

    private function effectiveValue(string $path): mixed
    {
        $definition = $this->catalog->findDefinition($path);
        $default = $definition['default'] ?? null;

        if (! $this->runtimeAvailable() || ! RuntimeConfig::has($path)) {
            return $default;
        }

        return RuntimeConfig::get($path);
    }

    private function runtimeAvailable(): bool
    {
        return app()->bound('config-store');
    }
}

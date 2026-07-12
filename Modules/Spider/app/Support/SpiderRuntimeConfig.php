<?php

declare(strict_types=1);

namespace Modules\Spider\Support;

use App\Support\CuratedConfigCatalog;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;

final class SpiderRuntimeConfig
{
    public function __construct(
        private readonly CuratedConfigCatalog $catalog,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->effectiveValue('spider.enabled');
    }

    public function maxDepth(): int
    {
        return (int) $this->effectiveValue('spider.max_depth');
    }

    public function maxNewContactsPerRun(): int
    {
        return (int) $this->effectiveValue('spider.max_new_contacts_per_run');
    }

    public function maxContactsTotal(): int
    {
        return (int) $this->effectiveValue('spider.max_contacts_total');
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

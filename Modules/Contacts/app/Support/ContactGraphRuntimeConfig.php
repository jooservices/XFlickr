<?php

declare(strict_types=1);

namespace Modules\Contacts\Support;

use App\Support\CuratedConfigCatalog;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;

final class ContactGraphRuntimeConfig
{
    public function __construct(
        private readonly CuratedConfigCatalog $catalog,
    ) {}

    public function initialDirectLimit(): int
    {
        return max(1, (int) $this->effectiveValue('contact_graph.initial_direct_limit'));
    }

    public function loadMoreStep(): int
    {
        return max(1, (int) $this->effectiveValue('contact_graph.load_more_step'));
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

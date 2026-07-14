<?php

declare(strict_types=1);

namespace Modules\Contacts\Support;

use App\Support\CuratedConfigCatalog;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;

final class ContactGraphRuntimeConfig
{
    /** Hard cap when UI requests "show all" (direct_limit=0). */
    public const int MAX_SHOW_ALL_DIRECT = 500;

    /** Max second-degree edges loaded into a snapshot (all subjects combined). */
    public const int MAX_SUBJECT_EDGES_TOTAL = 250;

    /** Max second-degree edges per visible direct contact. */
    public const int MAX_SUBJECT_EDGES_PER_SUBJECT = 5;

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

    public function resolveDirectLimit(int $requestedLimit, int $directTotal): int
    {
        if ($requestedLimit === 0) {
            return min($directTotal, max($this->initialDirectLimit(), self::MAX_SHOW_ALL_DIRECT));
        }

        return max(0, min($requestedLimit, $directTotal));
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

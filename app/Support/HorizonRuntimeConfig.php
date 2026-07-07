<?php

declare(strict_types=1);

namespace App\Support;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;

final class HorizonRuntimeConfig
{
    public const SUPERVISOR_GENERAL = 'supervisor-1';

    public const SUPERVISOR_DOWNLOADS = 'supervisor-downloads';

    public const SUPERVISOR_UPLOADS = 'supervisor-uploads';

    /** @var array<string, string> */
    private const SUPERVISOR_PATHS = [
        self::SUPERVISOR_GENERAL => 'horizon.general_max_processes',
        self::SUPERVISOR_DOWNLOADS => 'horizon.downloads_max_processes',
        self::SUPERVISOR_UPLOADS => 'horizon.uploads_max_processes',
    ];

    public function __construct(
        private readonly CuratedConfigCatalog $catalog,
    ) {}

    /**
     * @return array<string, string>
     */
    public function supervisorPaths(): array
    {
        return self::SUPERVISOR_PATHS;
    }

    public function effectiveMaxProcesses(string $supervisor): int
    {
        $path = self::SUPERVISOR_PATHS[$supervisor] ?? null;
        if ($path === null) {
            return 1;
        }

        return max(1, (int) $this->effectiveValue($path));
    }

    public function isHorizonPath(string $path): bool
    {
        return str_starts_with($path, 'horizon.');
    }

    public function assertValidWorkerCount(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 1;
        }

        $count = (int) $value;

        return min(32, max(1, $count));
    }

    private function effectiveValue(string $path): mixed
    {
        $definition = $this->catalog->findDefinition($path);
        $default = $definition['default'] ?? 1;

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

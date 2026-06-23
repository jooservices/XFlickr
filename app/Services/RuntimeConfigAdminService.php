<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ConfigEntryRepository;
use App\Support\CuratedConfigCatalog;
use Illuminate\Validation\ValidationException;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use JOOservices\LaravelConfig\Models\Config as ConfigModel;

final class RuntimeConfigAdminService
{
    public function __construct(
        private readonly CuratedConfigCatalog $catalog,
        private readonly ConfigEntryRepository $configEntries,
    ) {}

    /**
     * @return array{curated: list<array<string, mixed>>, custom: list<array<string, mixed>>}
     */
    public function indexPayload(): array
    {
        $curated = collect($this->catalog->definitions())
            ->map(function (array $definition): array {
                $path = $definition['path'];
                $stored = $this->runtimeAvailable() && RuntimeConfig::has($path);

                return [
                    ...$definition,
                    'effective_value' => $this->effectiveValue($path, $definition['default']),
                    'stored' => $stored,
                    'source' => $stored ? 'config-store' : 'default',
                ];
            })
            ->sortBy([
                ['group', 'asc'],
                ['sort', 'asc'],
                ['label', 'asc'],
            ])
            ->values()
            ->all();

        $custom = $this->customRecords();

        return [
            'curated' => $curated,
            'custom' => $custom,
        ];
    }

    /**
     * @param  array{path: string, type: string, value: mixed}  $data
     */
    public function upsert(array $data): void
    {
        if (! $this->runtimeAvailable()) {
            throw ValidationException::withMessages([
                'path' => 'Runtime config store is not available.',
            ]);
        }

        $path = trim($data['path']);
        $type = trim($data['type']);
        $value = $this->castValue($type, $data['value']);

        $this->assertAllowedPath($path);

        RuntimeConfig::set($path, $value, $type);
        RuntimeConfig::refresh();
    }

    public function delete(string $path): void
    {
        if (! $this->runtimeAvailable()) {
            throw ValidationException::withMessages([
                'path' => 'Runtime config store is not available.',
            ]);
        }

        if ($this->catalog->isCorePath($path)) {
            throw ValidationException::withMessages([
                'path' => 'Core configuration cannot be deleted.',
            ]);
        }

        $this->assertAllowedPath($path);

        if (! RuntimeConfig::has($path)) {
            return;
        }

        [$group, $key] = $this->splitPath($path);
        $this->configEntries->deleteByGroupAndKey($group, $key);
        RuntimeConfig::refresh();
    }

    public function resetToDefault(string $path): void
    {
        if (! $this->catalog->isCorePath($path)) {
            throw ValidationException::withMessages([
                'path' => 'Only core configuration can be reset to default.',
            ]);
        }

        $this->deleteStored($path);
    }

    private function deleteStored(string $path): void
    {
        if (! $this->runtimeAvailable() || ! RuntimeConfig::has($path)) {
            return;
        }

        [$group, $key] = $this->splitPath($path);
        $this->configEntries->deleteByGroupAndKey($group, $key);
        RuntimeConfig::refresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customRecords(): array
    {
        if (! $this->runtimeAvailable()) {
            return [];
        }

        $corePaths = $this->catalog->corePaths();
        $reservedPrefixes = ['xflickr_app.', 'storage_app.'];

        return $this->configEntries->listOrdered()
            ->map(function (ConfigModel $config) use ($corePaths, $reservedPrefixes): ?array {
                $group = (string) $config->getAttribute('group');
                $key = (string) $config->getAttribute('key');
                $path = "{$group}.{$key}";

                if (in_array($path, $corePaths, true)) {
                    return null;
                }

                foreach ($reservedPrefixes as $prefix) {
                    if (str_starts_with($path, $prefix)) {
                        return null;
                    }
                }

                return [
                    'path' => $path,
                    'type' => (string) $config->getAttribute('type'),
                    'value' => RuntimeConfig::get($path),
                    'is_core' => false,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function effectiveValue(string $path, mixed $default): mixed
    {
        if (! $this->runtimeAvailable() || ! RuntimeConfig::has($path)) {
            return $default;
        }

        return RuntimeConfig::get($path);
    }

    private function runtimeAvailable(): bool
    {
        return app()->bound('config-store');
    }

    private function assertAllowedPath(string $path): void
    {
        if (! preg_match('/^[^.\\s]+(?:\\.[^.\\s]+)+$/', $path)) {
            throw ValidationException::withMessages([
                'path' => 'Invalid configuration path.',
            ]);
        }

        if (str_starts_with($path, 'xflickr_app.') || str_starts_with($path, 'storage_app.')) {
            throw ValidationException::withMessages([
                'path' => 'Credential configuration must be managed on Flickr or Storages settings.',
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitPath(string $path): array
    {
        $parts = explode('.', $path, 2);
        if (count($parts) !== 2) {
            throw ValidationException::withMessages([
                'path' => 'Invalid configuration path.',
            ]);
        }

        return [$parts[0], $parts[1]];
    }

    private function castValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => is_numeric($value) ? (int) $value : 0,
            'float' => is_numeric($value) ? (float) $value : 0.0,
            'json', 'array' => is_string($value) ? json_decode($value, true) : $value,
            'null' => null,
            default => is_scalar($value) ? (string) $value : json_encode($value),
        };
    }
}

<?php

declare(strict_types=1);

namespace Modules\Settings\Services;

use App\Support\CuratedConfigCatalog;
use App\Support\HorizonRuntimeConfig;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;

final class RuntimeConfigAdminService
{
    public function __construct(
        private readonly CuratedConfigCatalog $catalog,
        private readonly HorizonRuntimeConfig $horizonConfig,
    ) {}

    /**
     * @return array{curated: list<array<string, mixed>>, custom: list<array<string, mixed>>}
     */
    public function indexPayload(): array
    {
        $curated = collect($this->catalog->definitions())
            ->map(function (array $definition): array {
                $path = $definition['path'];
                $stored = $this->isAvailable() && RuntimeConfig::has($path);

                return [
                    ...$definition,
                    'effective_value' => $this->effectiveValue($path, $definition['default']),
                    'stored' => $stored,
                    'source' => $stored ? 'config-store' : 'default',
                ];
            })
            ->sortBy([
                ['group_label', 'asc'],
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
        if (! $this->isAvailable()) {
            throw ValidationException::withMessages([
                'path' => 'Runtime config store is not available.',
            ]);
        }

        $path = trim($data['path']);
        $type = trim($data['type']);
        $value = $this->castValue($type, $data['value']);

        if ($this->horizonConfig->isHorizonPath($path)) {
            $value = $this->horizonConfig->assertValidWorkerCount($value);
        }

        $this->assertAllowedPath($path);

        RuntimeConfig::set($path, $value, $type);
        RuntimeConfig::refresh();

        if ($this->horizonConfig->isHorizonPath($path)) {
            Artisan::call('horizon:terminate');
        }
    }

    public function setGlobalCrawlPause(bool $paused): void
    {
        $this->upsert([
            'path' => 'xflickr.global_pause',
            'type' => 'bool',
            'value' => $paused,
        ]);
    }

    /**
     * @param  array{enabled: bool, max_depth: int, max_new_contacts_per_run: int, max_contacts_total: int}  $data
     */
    public function updateSpiderMode(array $data): void
    {
        $this->upsert([
            'path' => 'spider.enabled',
            'type' => 'bool',
            'value' => $data['enabled'],
        ]);
        $this->upsert([
            'path' => 'spider.max_depth',
            'type' => 'int',
            'value' => $data['max_depth'],
        ]);
        $this->upsert([
            'path' => 'spider.max_new_contacts_per_run',
            'type' => 'int',
            'value' => $data['max_new_contacts_per_run'],
        ]);
        $this->upsert([
            'path' => 'spider.max_contacts_total',
            'type' => 'int',
            'value' => $data['max_contacts_total'],
        ]);

        if ($data['enabled']) {
            $this->setGlobalCrawlPause(false);
        }
    }

    public function delete(string $path): void
    {
        if (! $this->isAvailable()) {
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

        RuntimeConfig::forget($path);
    }

    public function resetToDefault(string $path): void
    {
        if (! $this->catalog->isCorePath($path)) {
            throw ValidationException::withMessages([
                'path' => 'Only core configuration can be reset to default.',
            ]);
        }

        $this->deleteStored($path);

        if ($this->horizonConfig->isHorizonPath($path)) {
            Artisan::call('horizon:terminate');
        }
    }

    private function deleteStored(string $path): void
    {
        if (! $this->isAvailable() || ! RuntimeConfig::has($path)) {
            return;
        }

        RuntimeConfig::forget($path);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customRecords(): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        $corePaths = $this->catalog->corePaths();
        $reservedPrefixes = ['xflickr_app.', 'storage_app.'];

        return RuntimeConfig::listOrdered()
            ->map(function (array $config) use ($corePaths, $reservedPrefixes): ?array {
                $group = $config['group'];
                $key = $config['key'];
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
                    'id' => $path,
                    'path' => $path,
                    'type' => $config['type'],
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
        if (! $this->isAvailable() || ! RuntimeConfig::has($path)) {
            return $default;
        }

        return RuntimeConfig::get($path);
    }

    public function isAvailable(): bool
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

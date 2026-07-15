<?php

declare(strict_types=1);

namespace Modules\Transfer\Support;

use App\Support\CuratedConfigCatalog;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;

final class TransferRuntimeConfig
{
    private const DELETE_LOCAL_AFTER_UPLOAD_PATH = 'xflickr_transfer.delete_local_after_upload';

    public function __construct(
        private readonly CuratedConfigCatalog $catalog,
    ) {}

    public function shouldDeleteLocalAfterUpload(): bool
    {
        return (bool) $this->effectiveValue(self::DELETE_LOCAL_AFTER_UPLOAD_PATH);
    }

    private function effectiveValue(string $path): mixed
    {
        $definition = $this->catalog->findDefinition($path);
        $default = $definition['default'] ?? false;

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

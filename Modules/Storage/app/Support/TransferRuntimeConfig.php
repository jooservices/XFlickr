<?php

declare(strict_types=1);

namespace Modules\Storage\Support;

use App\Support\CuratedConfigCatalog;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;

final class TransferRuntimeConfig
{
    private const DELETE_LOCAL_AFTER_UPLOAD_PATH = 'xflickr_transfer.delete_local_after_upload';

    private const TIMEOUT_PATH = 'xflickr_download.timeout_seconds';

    private const MIN_TIMEOUT_SECONDS = 30;

    private const MAX_TIMEOUT_SECONDS = 900;

    public function __construct(
        private readonly CuratedConfigCatalog $catalog,
    ) {}

    public function shouldDeleteLocalAfterUpload(): bool
    {
        return (bool) $this->effectiveValue(
            self::DELETE_LOCAL_AFTER_UPLOAD_PATH,
            false,
        );
    }

    public function timeoutSeconds(): int
    {
        return $this->clampTimeout((int) $this->effectiveValue(
            self::TIMEOUT_PATH,
            config('xflickr.download.timeout_seconds', 120),
        ));
    }

    private function clampTimeout(int $seconds): int
    {
        return min(self::MAX_TIMEOUT_SECONDS, max(self::MIN_TIMEOUT_SECONDS, $seconds));
    }

    private function effectiveValue(string $path, mixed $fallback): mixed
    {
        $definition = $this->catalog->findDefinition($path);
        $default = $definition['default'] ?? $fallback;

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

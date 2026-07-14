<?php

declare(strict_types=1);

namespace Modules\Crawler\Support;

use Illuminate\Support\Facades\App;
use JOOservices\LaravelConfig\Contracts\ConfigStore;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\DTO\FlickrAppCredentialsDto;
use Modules\Crawler\Exceptions\FlickrAppNotConfiguredException;

final class XFlickrConfig
{
    public static function get(string $path, mixed $default = null): mixed
    {
        if (self::runtimeAvailable() && RuntimeConfig::has($path)) {
            return RuntimeConfig::get($path);
        }

        return $default;
    }

    public static function bool(string $path, bool $default = false): bool
    {
        $value = self::get($path, $default);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function int(string $path, int $default = 0): int
    {
        $value = self::get($path, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function throttle(string $key, int $default): int
    {
        $runtimeKey = "xflickr.{$key}";
        if (self::runtimeAvailable() && RuntimeConfig::has($runtimeKey)) {
            return self::int($runtimeKey, $default);
        }

        return (int) config("xflickr-crawler.throttle.{$key}", $default);
    }

    public static function crawl(string $key, mixed $default = null): mixed
    {
        $runtimeKey = "xflickr_crawl.{$key}";
        if (self::runtimeAvailable() && self::runtimeHas($runtimeKey)) {
            return RuntimeConfig::get($runtimeKey);
        }

        return config("xflickr-crawler.crawl.{$key}", $default);
    }

    public static function crawlInt(string $key, int $default = 0): int
    {
        $value = self::crawl($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function globalPause(): bool
    {
        return self::bool('xflickr.global_pause', false);
    }

    public static function maxRequestsPerHour(): int
    {
        $runtime = self::int('xflickr.max_requests_per_hour', 0);

        return $runtime > 0
            ? $runtime
            : self::throttle('max_requests_per_hour', 3300);
    }

    public static function dispatchLimit(): int
    {
        $runtime = self::int('xflickr.dispatch_limit', 0);

        $configured = self::crawlInt('dispatch_limit', 0);

        return $runtime > 0
            ? $runtime
            : $configured;
    }

    public static function table(string $key): string
    {
        return (string) config("xflickr-crawler.tables.{$key}", "xflickr_{$key}");
    }

    public static function defaultAppProfile(): string
    {
        $runtime = self::get('xflickr.default_app_profile');
        if (is_string($runtime) && $runtime !== '') {
            return self::sanitizeProfileSlug($runtime);
        }

        return self::sanitizeProfileSlug((string) config('xflickr-crawler.default_app_profile', 'default'));
    }

    public static function appCredentials(string $appProfile): FlickrAppCredentialsDto
    {
        $profile = self::sanitizeProfileSlug($appProfile);
        $path = "xflickr_app.{$profile}";

        if (! self::runtimeAvailable() || ! RuntimeConfig::has($path)) {
            throw FlickrAppNotConfiguredException::forProfile($profile);
        }

        $value = RuntimeConfig::get($path);
        if (! is_array($value)) {
            throw FlickrAppNotConfiguredException::forProfile($profile);
        }

        $apiKey = trim((string) ($value['apiKey'] ?? $value['api_key'] ?? ''));
        $apiSecret = trim((string) ($value['apiSecret'] ?? $value['api_secret'] ?? ''));
        $label = isset($value['label']) ? trim((string) $value['label']) : null;

        if ($apiKey === '' || $apiSecret === '') {
            throw FlickrAppNotConfiguredException::forProfile($profile);
        }

        return new FlickrAppCredentialsDto(
            apiKey: $apiKey,
            apiSecret: $apiSecret,
            label: $label !== '' ? $label : null,
        );
    }

    public static function sanitizeProfileSlug(string $profile): string
    {
        $normalized = strtolower(trim($profile));

        if ($normalized === '' || ! preg_match('/^[a-z0-9_-]+$/', $normalized)) {
            throw FlickrAppNotConfiguredException::invalidProfile($profile);
        }

        return $normalized;
    }

    private static function runtimeAvailable(): bool
    {
        $application = App::getFacadeApplication();

        return $application?->bound(ConfigStore::class) === true
            || $application?->bound('config-store') === true;
    }

    private static function runtimeHas(string $path): bool
    {
        if (substr_count($path, '.') !== 1) {
            return false;
        }

        return RuntimeConfig::has($path);
    }
}

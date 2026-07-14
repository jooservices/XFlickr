<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use App\Repositories\Crawler\ConnectionQueryRepository;
use App\Support\MaskedCredentialHint;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Support\XFlickrConfig;

final class FlickrAppProfileService
{
    public function __construct(
        private readonly ConnectionQueryRepository $connections,
    ) {}

    /**
     * @return Collection<int, mixed>
     */
    public function listPublic(): Collection
    {
        if (! RuntimeConfig::has('xflickr_app.main') && RuntimeConfig::group('xflickr_app') === []) {
            return collect();
        }

        return collect(RuntimeConfig::group('xflickr_app'))
            ->map(function (mixed $value, string $profile): ?array {
                if (! is_array($value)) {
                    return null;
                }

                $apiKey = trim((string) ($value['apiKey'] ?? $value['api_key'] ?? ''));
                if ($apiKey === '') {
                    return null;
                }

                $label = isset($value['label']) ? trim((string) $value['label']) : null;
                $callbackUrl = trim((string) ($value['callbackUrl'] ?? $value['callback_url'] ?? ''));

                return [
                    'profile' => $profile,
                    'label' => $label !== '' ? $label : null,
                    'api_key_hint' => MaskedCredentialHint::leadingAndTrailing($apiKey),
                    'callback_url' => $callbackUrl !== '' ? $callbackUrl : $this->defaultCallbackUrl(),
                    'accounts_count' => $this->connections->countByAppProfile($profile),
                ];
            })
            ->filter()
            ->sortBy('profile')
            ->values();
    }

    /**
     * @param  array{profile?: string, label?: string|null, api_key: string, api_secret: string, callback_url?: string|null}  $data
     */
    public function save(array $data): string
    {
        $profile = XFlickrConfig::sanitizeProfileSlug($data['profile'] ?? 'main');

        $apiKey = trim($data['api_key']);
        $apiSecret = trim($data['api_secret']);

        if ($apiKey === '' || $apiSecret === '') {
            throw ValidationException::withMessages([
                'api_key' => 'API key and secret are required.',
            ]);
        }

        $label = isset($data['label']) ? trim((string) $data['label']) : null;
        $callbackUrl = isset($data['callback_url']) ? trim((string) $data['callback_url']) : null;

        RuntimeConfig::set("xflickr_app.{$profile}", [
            'apiKey' => $apiKey,
            'apiSecret' => $apiSecret,
            'label' => $label !== '' ? $label : null,
            'callbackUrl' => $this->resolveCallbackUrl($callbackUrl),
        ], 'json');

        RuntimeConfig::refresh();

        return $profile;
    }

    public function delete(string $profile): string
    {
        $slug = XFlickrConfig::sanitizeProfileSlug($profile);

        if (! RuntimeConfig::has("xflickr_app.{$slug}")) {
            throw ValidationException::withMessages([
                'profile' => 'Flickr app profile not found.',
            ]);
        }

        if ($this->connections->countByAppProfile($slug) > 0) {
            throw ValidationException::withMessages([
                'profile' => 'Disconnect all Flickr accounts using this profile before deleting it.',
            ]);
        }

        RuntimeConfig::forget("xflickr_app.{$slug}");
        RuntimeConfig::refresh();

        return $slug;
    }

    public function hasProfiles(): bool
    {
        return $this->listPublic()->isNotEmpty();
    }

    /**
     * @return array{apiKey: string, apiSecret: string, callbackUrl: string}
     */
    public function flickrClientConfig(string $profile): array
    {
        $slug = XFlickrConfig::sanitizeProfileSlug($profile);
        $credentials = XFlickrConfig::appCredentials($slug);
        $stored = RuntimeConfig::get("xflickr_app.{$slug}");
        $callbackUrl = is_array($stored)
            ? trim((string) ($stored['callbackUrl'] ?? $stored['callback_url'] ?? ''))
            : '';

        return [
            'apiKey' => $credentials->apiKey,
            'apiSecret' => $credentials->apiSecret,
            'callbackUrl' => $this->resolveCallbackUrl($callbackUrl !== '' ? $callbackUrl : null),
        ];
    }

    public function defaultCallbackUrl(): string
    {
        return $this->resolveCallbackUrl(null);
    }

    private function resolveCallbackUrl(?string $stored): string
    {
        if ($stored !== null && $stored !== '') {
            return $stored;
        }

        $envCallback = config('services.flickr.callback_url');
        if (is_string($envCallback) && $envCallback !== '') {
            return $envCallback;
        }

        return route('flickr.callback', [], true);
    }
}

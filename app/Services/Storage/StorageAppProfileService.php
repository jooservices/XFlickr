<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Enums\StorageDriver;
use App\Repositories\StorageAccountRepository;
use App\Support\MaskedCredentialHint;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;

final class StorageAppProfileService
{
    public function __construct(
        private readonly StorageAccountRepository $accounts,
    ) {}

    /**
     * @return Collection<int, array{provider: string, label: string, client_id_hint: string, redirect: string|null, accounts_count: int}>
     */
    public function listPublic(): Collection
    {
        return collect(StorageDriver::credentialProviders())
            ->map(function (StorageDriver $driver): ?array {
                $path = "storage_app.{$driver->value}";
                if (! RuntimeConfig::has($path)) {
                    return null;
                }

                $value = RuntimeConfig::get($path);
                if (! is_array($value)) {
                    return null;
                }

                $clientId = trim((string) ($value['client_id'] ?? $value['clientId'] ?? ''));
                if ($clientId === '') {
                    return null;
                }

                $label = trim((string) ($value['label'] ?? $driver->label()));
                $redirect = trim((string) ($value['redirect'] ?? $value['redirect_uri'] ?? ''));

                return [
                    'provider' => $driver->value,
                    'label' => $label !== '' ? $label : $driver->label(),
                    'client_id_hint' => MaskedCredentialHint::leadingAndTrailing($clientId),
                    'redirect' => $redirect !== '' ? $redirect : $this->defaultRedirectUri($driver),
                    'accounts_count' => $this->accounts->countForProvider($driver->value),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array{provider: string, label?: string|null, client_id: string, client_secret: string, redirect?: string|null}  $data
     */
    public function save(array $data): string
    {
        $driver = StorageDriver::from($data['provider']);
        $clientId = trim($data['client_id']);
        $clientSecret = trim($data['client_secret']);

        if ($clientId === '' || $clientSecret === '') {
            throw ValidationException::withMessages([
                'client_id' => 'Client ID and secret are required.',
            ]);
        }

        $label = isset($data['label']) ? trim((string) $data['label']) : null;
        $redirect = isset($data['redirect']) ? trim((string) $data['redirect']) : '';

        RuntimeConfig::set("storage_app.{$driver->value}", [
            'label' => $label !== '' ? $label : $driver->label(),
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect' => $redirect !== '' ? $redirect : $this->defaultRedirectUri($driver),
        ], 'json');

        RuntimeConfig::refresh();

        return $driver->value;
    }

    public function hasProfiles(): bool
    {
        return $this->listPublic()->isNotEmpty();
    }

    public function defaultRedirectUri(StorageDriver $driver): string
    {
        return url("/storage/callback/{$driver->value}");
    }

    /**
     * @return array<string, string>
     */
    public function defaultRedirects(): array
    {
        return collect(StorageDriver::credentialProviders())
            ->mapWithKeys(fn (StorageDriver $driver): array => [
                $driver->value => $this->defaultRedirectUri($driver),
            ])
            ->all();
    }
}

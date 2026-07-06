<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Enums\StorageDriver;
use App\Repositories\StorageAccountRepository;
use App\Support\Storage\StorageAccountPresenter;
use Illuminate\Support\Collection;

final class StorageSettingsService
{
    public function __construct(
        private readonly StorageAccountRepository $accounts,
        private readonly StorageAppProfileService $appProfiles,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function accounts(): Collection
    {
        return $this->accounts->listOrderedForSettings()
            ->map(fn ($account): array => StorageAccountPresenter::toPublicArray($account))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function apps(): Collection
    {
        return $this->appProfiles->listPublic()->values();
    }

    /**
     * @return array<string, string>
     */
    public function redirects(): array
    {
        return $this->appProfiles->defaultRedirects();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function drivers(): Collection
    {
        return collect(StorageDriver::all())->map(fn (StorageDriver $driver): array => [
            'value' => $driver->value,
            'label' => $driver->label(),
            'requires_oauth' => $driver->requiresOAuth(),
            'requires_app' => $driver->requiresApp(),
            'requires_account' => $driver->requiresAccount(),
        ])->values();
    }
}

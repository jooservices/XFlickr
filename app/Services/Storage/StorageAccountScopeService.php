<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Enums\StorageDriver;
use App\Models\StorageAccount;

final class StorageAccountScopeService
{
    /**
     * @return list<string>
     */
    public function missingScopes(StorageAccount $account): array
    {
        try {
            $driver = StorageDriver::from($account->provider);
        } catch (\ValueError) {
            return [];
        }

        if (! $driver->requiresOAuth()) {
            return [];
        }

        $required = $driver->defaultScopes();
        $credentials = $account->credentials ?? [];
        $granted = $credentials['granted_scopes'] ?? null;

        if (! is_array($granted) || $granted === []) {
            return $required;
        }

        $grantedScopes = array_values(array_filter($granted, is_string(...)));

        return array_values(array_diff($required, $grantedScopes));
    }

    public function needsReauthorization(StorageAccount $account): bool
    {
        return $this->missingScopes($account) !== [];
    }

    /**
     * @return list<array{scope: string, label: string}>
     */
    public function missingScopeDefinitions(StorageAccount $account): array
    {
        try {
            $driver = StorageDriver::from($account->provider);
        } catch (\ValueError) {
            return [];
        }

        $definitions = [];
        foreach ($this->missingScopes($account) as $scope) {
            $definitions[] = [
                'scope' => $scope,
                'label' => $driver->scopeLabel($scope),
            ];
        }

        return $definitions;
    }

    /**
     * @return array<string, mixed>
     */
    public function authorizationMeta(StorageAccount $account): array
    {
        $missing = $this->missingScopeDefinitions($account);

        return [
            'needs_reauthorization' => $missing !== [],
            'missing_scopes' => $missing,
            'reauthorize_url' => route('storage.reauthorize', ['account' => $account->id]),
        ];
    }
}

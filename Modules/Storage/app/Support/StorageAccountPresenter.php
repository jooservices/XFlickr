<?php

declare(strict_types=1);

namespace Modules\Storage\Support;

use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageAccountScopeService;

final class StorageAccountPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toPublicArray(StorageAccount $account): array
    {
        $authorization = app(StorageAccountScopeService::class)->authorizationMeta($account);
        $credentials = $account->credentials ?? [];

        return [
            'id' => $account->id,
            'provider' => $account->provider,
            'label' => $account->label,
            'is_default' => $account->is_default,
            'connected_at' => $account->connected_at?->toIso8601String(),
            'needs_reauthorization' => $authorization['needs_reauthorization'],
            'missing_scopes' => $authorization['missing_scopes'],
            'reauthorize_url' => $authorization['reauthorize_url'],
            'connection_meta' => self::connectionMeta($account, $credentials),
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>|null
     */
    private static function connectionMeta(StorageAccount $account, array $credentials): ?array
    {
        if (StorageDriver::tryFrom($account->provider) !== StorageDriver::R2) {
            return null;
        }

        return [
            'bucket' => $credentials['bucket'] ?? null,
            'endpoint' => $credentials['endpoint'] ?? null,
            'prefix' => $credentials['prefix'] ?? null,
        ];
    }
}

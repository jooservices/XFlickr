<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Modules\Storage\Events\StorageAccountConnected;
use Modules\Storage\Events\StorageAccountDisconnected;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Repositories\StorageAccountRepository;

final class StorageAccountService
{
    public function __construct(
        private readonly StorageAccountRepository $accounts,
    ) {}

    public function find(int $id): ?StorageAccount
    {
        return $this->accounts->findById($id);
    }

    /**
     * @param  array{access_token: string, refresh_token?: string|null, expires_in?: int|null, token_type?: string|null, client_id?: string, client_secret?: string, granted_scopes?: list<string>}  $tokens
     */
    public function connect(string $provider, string $accountIdentifier, array $tokens, ?string $label = null): StorageAccount
    {
        return $this->accounts->connectInTransaction(function () use ($provider, $accountIdentifier, $tokens, $label): StorageAccount {
            $account = $this->accounts->updateOrCreateByProviderAndLabel(
                $provider,
                $label ?? $accountIdentifier,
                [
                    'credentials' => $this->buildCredentials($tokens),
                    'connected_at' => now(),
                ],
            );

            if (! $this->accounts->hasDefaultForProvider($provider)) {
                $account->update(['is_default' => true]);
            }

            $account = $account->fresh() ?? $account;

            event(new StorageAccountConnected(
                accountId: $account->id,
                provider: $provider,
                label: $account->label,
            ));

            return $account;
        });
    }

    /**
     * @param  array{access_token: string, refresh_token?: string|null, expires_in?: int|null, token_type?: string|null, client_id?: string, client_secret?: string, granted_scopes?: list<string>}  $tokens
     */
    public function reauthorize(StorageAccount $account, array $tokens): StorageAccount
    {
        $existing = $account->credentials ?? [];
        $mergedTokens = $tokens;

        if (empty($mergedTokens['refresh_token']) && ! empty($existing['refresh_token'])) {
            $mergedTokens['refresh_token'] = $existing['refresh_token'];
        }

        if (empty($mergedTokens['client_id']) && ! empty($existing['client_id'])) {
            $mergedTokens['client_id'] = $existing['client_id'];
        }

        if (empty($mergedTokens['client_secret']) && ! empty($existing['client_secret'])) {
            $mergedTokens['client_secret'] = $existing['client_secret'];
        }

        $account->update([
            'credentials' => $this->buildCredentials($mergedTokens),
            'connected_at' => now(),
        ]);

        return $account->fresh() ?? $account;
    }

    public function disconnect(StorageAccount $account): void
    {
        $wasDefault = $account->is_default;
        $provider = $account->provider;
        $accountId = $account->id;

        event(new StorageAccountDisconnected(
            accountId: $accountId,
            provider: $provider,
        ));

        $account->delete();

        if ($wasDefault) {
            $this->accounts->promoteFirstAsDefault($provider);
        }
    }

    public function setDefault(StorageAccount $account): void
    {
        $this->accounts->connectInTransaction(function () use ($account): StorageAccount {
            $this->accounts->clearDefaultForProvider($account->provider);
            $account->update(['is_default' => true]);

            return $account->fresh() ?? $account;
        });
    }

    /**
     * @param  array{access_token: string, refresh_token?: string|null, expires_in?: int|null, token_type?: string|null, client_id?: string, client_secret?: string, granted_scopes?: list<string>}  $tokens
     * @return array<string, mixed>
     */
    public function buildCredentials(array $tokens): array
    {
        $credentials = [
            'access_token' => $tokens['access_token'],
        ];

        if (! empty($tokens['refresh_token'])) {
            $credentials['refresh_token'] = $tokens['refresh_token'];
        }

        if (isset($tokens['expires_in']) && is_numeric($tokens['expires_in'])) {
            $credentials['expires_at'] = now()->addSeconds((int) $tokens['expires_in'])->toIso8601String();
        }

        if (! empty($tokens['token_type'])) {
            $credentials['token_type'] = $tokens['token_type'];
        }

        if (! empty($tokens['client_id'])) {
            $credentials['client_id'] = $tokens['client_id'];
        }

        if (! empty($tokens['client_secret'])) {
            $credentials['client_secret'] = $tokens['client_secret'];
        }

        if (! empty($tokens['granted_scopes']) && is_array($tokens['granted_scopes'])) {
            $credentials['granted_scopes'] = array_values(array_filter(
                $tokens['granted_scopes'],
                fn (mixed $scope): bool => is_string($scope) && $scope !== '',
            ));
        }

        return $credentials;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function connectApiKey(string $provider, string $label, array $credentials): StorageAccount
    {
        return $this->accounts->connectInTransaction(function () use ($provider, $label, $credentials): StorageAccount {
            $account = $this->accounts->updateOrCreateByProviderAndLabel(
                $provider,
                $label,
                [
                    'credentials' => $credentials,
                    'connected_at' => now(),
                ],
            );

            if (! $this->accounts->hasDefaultForProvider($provider)) {
                $account->update(['is_default' => true]);
            }

            $account = $account->fresh() ?? $account;

            event(new StorageAccountConnected(
                accountId: $account->id,
                provider: $provider,
                label: $account->label,
            ));

            return $account;
        });
    }
}

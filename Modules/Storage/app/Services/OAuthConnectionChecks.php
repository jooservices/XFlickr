<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use App\Support\MaskedCredentialHint;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Storage\Dto\ConnectionCheck;
use Modules\Storage\Enums\ConnectionCheckStatus;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;

final class OAuthConnectionChecks
{
    public function __construct(
        private readonly StorageAccountScopeService $scopes,
    ) {}

    public function oauthApp(StorageAccount $account, StorageDriver $driver): ConnectionCheck
    {
        $configured = $this->configuredClientId($driver);
        $stored = trim((string) (($account->credentials ?? [])['client_id'] ?? ''));

        $details = [
            'Configured in Settings: '.($configured !== '' ? MaskedCredentialHint::leadingAndTrailing($configured) : 'not set'),
            'Stored on account:      '.($stored !== '' ? MaskedCredentialHint::leadingAndTrailing($stored) : 'not set'),
        ];

        if ($configured === '' || $stored === '') {
            return ConnectionCheck::warning('OAuth app', 'Client ID is not fully configured.', $details);
        }

        if (! hash_equals($configured, $stored)) {
            return ConnectionCheck::warning('OAuth app', 'Client IDs do not match — this account was connected with a different OAuth app.', $details);
        }

        return ConnectionCheck::passed('OAuth app', 'Client IDs match.', $details);
    }

    public function authorization(StorageAccount $account): ConnectionCheck
    {
        $missing = $this->scopes->missingScopeDefinitions($account);
        if ($missing === []) {
            return ConnectionCheck::passed('Authorization', 'All required OAuth scopes are granted.');
        }

        return ConnectionCheck::failed(
            'Authorization',
            'Additional scopes required — reauthorize this account in Settings → Storages.',
            array_map(static fn (array $scope): string => $scope['label'], $missing),
        );
    }

    public function probeAllowed(ConnectionCheck $authorization): bool
    {
        return $authorization->status !== ConnectionCheckStatus::Failed;
    }

    private function configuredClientId(StorageDriver $driver): string
    {
        $path = 'storage_app.'.$driver->value;
        if (! RuntimeConfig::has($path)) {
            return '';
        }

        $value = RuntimeConfig::get($path);
        if (! is_array($value)) {
            return '';
        }

        return trim((string) ($value['client_id'] ?? $value['clientId'] ?? ''));
    }
}

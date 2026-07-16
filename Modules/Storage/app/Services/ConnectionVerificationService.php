<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Modules\Storage\Contracts\StorageVerifiable;
use Modules\Storage\Dto\ConnectionCheck;
use Modules\Storage\Dto\ConnectionReport;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Repositories\StorageAccountRepository;

final class ConnectionVerificationService
{
    public function __construct(
        private readonly StorageAccountRepository $accounts,
        private readonly StorageAdapterFactory $factory,
    ) {}

    /**
     * @return list<ConnectionReport>
     */
    public function verifyAll(?StorageDriver $provider = null): array
    {
        $accounts = $provider !== null
            ? $this->accounts->listForProvider($provider->value)
            : $this->accounts->listOrderedForSettings();

        return $accounts
            ->map(fn (StorageAccount $account): ConnectionReport => $this->verify($account))
            ->all();
    }

    public function verifyAccount(int $accountId): ?ConnectionReport
    {
        $account = $this->accounts->findById($accountId);

        return $account === null ? null : $this->verify($account);
    }

    public function verify(StorageAccount $account): ConnectionReport
    {
        $driver = StorageDriver::tryFrom($account->provider);
        if ($driver === null) {
            return new ConnectionReport(
                accountId: $account->id,
                accountLabel: $account->label,
                provider: $account->provider,
                providerLabel: $account->provider,
                checks: [ConnectionCheck::failed('Provider', "Unknown storage provider [{$account->provider}].")],
            );
        }

        $adapter = $this->factory->make($account);
        if (! $adapter instanceof StorageVerifiable) {
            return new ConnectionReport(
                accountId: $account->id,
                accountLabel: $account->label,
                provider: $driver->value,
                providerLabel: $driver->label(),
                checks: [ConnectionCheck::warning(
                    'Verification',
                    'Connection verification is not supported for this provider.',
                )],
            );
        }

        return $adapter->verify();
    }
}

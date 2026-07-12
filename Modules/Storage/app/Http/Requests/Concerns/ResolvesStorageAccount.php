<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Requests\Concerns;

use Illuminate\Http\Exceptions\HttpResponseException;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Repositories\StorageAccountRepository;

trait ResolvesStorageAccount
{
    /**
     * @return array<string, mixed>
     */
    protected function storageAccountRules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function storageAccountMessages(): array
    {
        return [
            'account_id.required' => 'account_id is required.',
        ];
    }

    public function messages(): array
    {
        return $this->storageAccountMessages();
    }

    public function accountId(): int
    {
        return (int) $this->input('account_id', $this->query('account_id', 0));
    }

    public function account(StorageDriver $driver): StorageAccount
    {
        $accountId = $this->accountId();

        if ($accountId <= 0) {
            throw new HttpResponseException(
                response()->json(['message' => 'account_id is required.'], 422),
            );
        }

        $account = app(StorageAccountRepository::class)->findById($accountId);

        if ($account === null || $account->provider !== $driver->value) {
            throw new HttpResponseException(
                response()->json(['message' => 'Storage account not found for this provider.'], 422),
            );
        }

        return $account;
    }

    public function containerId(): ?string
    {
        $containerId = $this->query('container_id', $this->input('container_id'));

        return is_string($containerId) && $containerId !== '' ? $containerId : null;
    }
}

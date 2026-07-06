<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

use App\Enums\StorageDriver;
use App\Http\Requests\Request;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class BeginStorageOAuthRequest extends Request
{
    protected function prepareForValidation(): void
    {
        $accountId = $this->query('account_id');
        if ($accountId === '' || $accountId === null || (is_numeric($accountId) && (int) $accountId <= 0)) {
            $accountId = null;
        }

        $this->merge([
            'provider' => $this->route('provider'),
            'account_id' => $accountId,
            'return_url' => $this->sanitizeReturnUrl($this->query('return_url')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(array_map(
                static fn (StorageDriver $driver): string => $driver->value,
                StorageDriver::cases(),
            ))],
            'account_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'return_url' => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('settings.index', ['tab' => 'storage'])->with(
                'error',
                'Storage OAuth could not be started. Add app credentials for this provider in Settings first.',
            ),
        );
    }

    public function driver(): StorageDriver
    {
        return StorageDriver::from((string) $this->validated('provider'));
    }

    public function accountId(): ?int
    {
        $accountId = $this->validated('account_id');

        return is_numeric($accountId) && (int) $accountId > 0 ? (int) $accountId : null;
    }

    public function returnUrl(): ?string
    {
        $returnUrl = $this->validated('return_url');

        return is_string($returnUrl) && $returnUrl !== '' ? $returnUrl : null;
    }

    private function sanitizeReturnUrl(mixed $returnUrl): ?string
    {
        if (! is_string($returnUrl) || $returnUrl === '') {
            return null;
        }

        if (! str_starts_with($returnUrl, '/') || str_starts_with($returnUrl, '//')) {
            return null;
        }

        return $returnUrl;
    }
}

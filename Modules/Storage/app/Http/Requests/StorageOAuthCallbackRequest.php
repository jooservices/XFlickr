<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;
use Modules\Storage\Enums\StorageDriver;

final class StorageOAuthCallbackRequest extends Request
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => $this->route('provider'),
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
            'error' => ['sometimes', 'nullable', 'string'],
            'code' => ['required_without:error', 'nullable', 'string'],
            'state' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function provider(): string
    {
        return (string) $this->validated('provider');
    }

    public function hasOAuthError(): bool
    {
        return (string) $this->query('error', '') !== '';
    }

    public function code(): string
    {
        return (string) $this->query('code', '');
    }

    public function state(): string
    {
        return (string) $this->query('state', '');
    }
}

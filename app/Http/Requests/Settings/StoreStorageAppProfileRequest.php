<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\StorageDriver;
use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

final class StoreStorageAppProfileRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(array_map(
                fn (StorageDriver $driver): string => $driver->value,
                StorageDriver::credentialProviders(),
            ))],
            'label' => ['nullable', 'string', 'max:255'],
            'client_id' => ['required', 'string', 'max:2048'],
            'client_secret' => ['required', 'string', 'max:2048'],
            'redirect' => ['nullable', 'url', 'max:2048'],
        ];
    }
}

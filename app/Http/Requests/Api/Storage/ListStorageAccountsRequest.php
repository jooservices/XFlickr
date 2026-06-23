<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Storage;

use App\Enums\StorageDriver;
use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

final class ListStorageAccountsRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(array_map(
                fn (StorageDriver $driver): string => $driver->value,
                StorageDriver::all(),
            ))],
        ];
    }

    public function driver(): StorageDriver
    {
        return StorageDriver::from((string) $this->query('provider', ''));
    }
}

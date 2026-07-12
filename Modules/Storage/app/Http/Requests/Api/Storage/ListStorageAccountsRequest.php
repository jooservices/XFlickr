<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Requests\Api\Storage;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;
use Modules\Storage\Enums\StorageDriver;

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

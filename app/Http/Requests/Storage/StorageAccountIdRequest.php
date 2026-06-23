<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

use App\Http\Requests\Request;

final class StorageAccountIdRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function accountId(): int
    {
        return (int) $this->validated('account_id');
    }
}

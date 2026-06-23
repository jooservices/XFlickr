<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

use App\Http\Requests\Request;

final class StorageOAuthCallbackRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'error' => ['sometimes', 'nullable', 'string'],
            'code' => ['required_without:error', 'nullable', 'string'],
            'state' => ['sometimes', 'nullable', 'string'],
        ];
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

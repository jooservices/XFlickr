<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Storage;

use App\Http\Requests\Concerns\ResolvesStorageAccount;
use App\Http\Requests\Request;

final class DownloadStorageFileRequest extends Request
{
    use ResolvesStorageAccount;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->storageAccountRules(),
            'path' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->storageAccountMessages(),
            'path.required' => 'path is required.',
        ];
    }

    public function path(): string
    {
        return (string) $this->query('path', '');
    }
}

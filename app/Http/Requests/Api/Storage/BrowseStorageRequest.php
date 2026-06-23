<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Storage;

use App\Http\Requests\Concerns\NormalizesPagination;
use App\Http\Requests\Concerns\ResolvesStorageAccount;
use App\Http\Requests\Request;

final class BrowseStorageRequest extends Request
{
    use NormalizesPagination;
    use ResolvesStorageAccount;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->storageAccountRules(),
            ...$this->paginationRules(),
            'source' => ['sometimes', 'string', 'in:local,provider'],
            'container_id' => ['sometimes', 'nullable', 'string'],
            'album_page_token' => ['sometimes', 'nullable', 'string'],
            'item_page_token' => ['sometimes', 'nullable', 'string'],
            'album_page' => ['sometimes', 'integer', 'min:1'],
            'item_page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->storageAccountMessages();
    }

    public function source(): string
    {
        return (string) $this->query('source', 'local');
    }

    public function albumPageToken(): ?string
    {
        $token = $this->query('album_page_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function itemPageToken(): ?string
    {
        $token = $this->query('item_page_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function albumPage(): int
    {
        return max(1, (int) $this->query('album_page', 1));
    }

    public function itemPage(): int
    {
        return max(1, (int) $this->query('item_page', 1));
    }
}

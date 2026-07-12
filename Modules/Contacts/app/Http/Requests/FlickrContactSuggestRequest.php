<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Requests;

use App\Http\Requests\Request;

final class FlickrContactSuggestRequest extends Request
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => trim((string) $this->query('search', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function search(): string
    {
        return trim((string) $this->input('search', ''));
    }

    public function limit(): int
    {
        return min(20, max(1, (int) $this->query('limit', 8)));
    }

    public function isSearchable(): bool
    {
        return mb_strlen($this->search()) >= 2;
    }
}

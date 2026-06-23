<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait NormalizesPagination
{
    protected function defaultPage(): int
    {
        return 1;
    }

    protected function defaultPerPage(): int
    {
        return 25;
    }

    /**
     * @return array<string, mixed>
     */
    protected function paginationRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function page(): int
    {
        return max(1, (int) $this->input('page', $this->defaultPage()));
    }

    public function perPage(): int
    {
        return min(100, max(1, (int) $this->input('per_page', $this->defaultPerPage())));
    }
}

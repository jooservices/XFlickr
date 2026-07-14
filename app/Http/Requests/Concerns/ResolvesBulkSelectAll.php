<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait ResolvesBulkSelectAll
{
    protected function prepareBulkSelectAllForValidation(): void
    {
        if ($this->has('select_all')) {
            $this->merge([
                'select_all' => filter_var($this->input('select_all'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('starred_only')) {
            $this->merge([
                'starred_only' => filter_var($this->input('starred_only'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('search')) {
            $this->merge([
                'search' => trim((string) $this->input('search', '')),
            ]);
        }

        if ($this->has('owner_nsid')) {
            $this->merge([
                'owner_nsid' => trim((string) $this->input('owner_nsid', '')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function bulkSelectAllRules(): array
    {
        return [
            'select_all' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'nullable', 'string'],
            'starred_only' => ['sometimes', 'boolean'],
            'owner_nsid' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function wantsSelectAll(): bool
    {
        return $this->boolean('select_all');
    }

    public function bulkSearch(): ?string
    {
        $search = trim((string) $this->input('search', ''));

        return $search !== '' ? $search : null;
    }

    public function bulkStarredOnly(): bool
    {
        return $this->boolean('starred_only');
    }

    public function bulkOwnerNsid(): ?string
    {
        $ownerNsid = trim((string) $this->input('owner_nsid', ''));

        return $ownerNsid !== '' ? $ownerNsid : null;
    }
}

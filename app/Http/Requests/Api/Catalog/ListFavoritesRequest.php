<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Catalog;

final class ListFavoritesRequest extends CatalogListRequest
{
    protected function prepareForValidation(): void
    {
        $subjectNsid = $this->query('subject_nsid', $this->query('owner_nsid'));

        if (is_string($subjectNsid) && $subjectNsid !== '') {
            $this->merge(['subject_nsid' => $subjectNsid]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            'subject_nsid' => ['sometimes', 'nullable', 'string'],
            'owner_nsid' => ['sometimes', 'nullable', 'string'],
            'connection_key' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowedSorts(): array
    {
        return ['subject_nsid', 'photo_owner_nsid', 'xflickr_photo_id', 'discovered_at', 'id'];
    }

    public function subjectNsid(): ?string
    {
        $subjectNsid = $this->query('subject_nsid', $this->query('owner_nsid'));

        return is_string($subjectNsid) && $subjectNsid !== '' ? $subjectNsid : null;
    }

    public function connectionKey(): ?string
    {
        $connectionKey = $this->query('connection_key');

        return is_string($connectionKey) && $connectionKey !== '' ? $connectionKey : null;
    }
}

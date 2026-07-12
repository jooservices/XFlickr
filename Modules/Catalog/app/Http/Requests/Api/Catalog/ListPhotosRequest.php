<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests\Api\Catalog;

final class ListPhotosRequest extends CatalogListRequest
{
    /**
     * @return list<string>
     */
    protected function allowedSorts(): array
    {
        return ['title', 'flickr_photo_id', 'owner_nsid', 'id'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            ...parent::filterRules(),
            'photoset_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function photosetId(): ?int
    {
        $photosetId = $this->query('photoset_id');

        if (! is_numeric($photosetId)) {
            return null;
        }

        $parsed = (int) $photosetId;

        return $parsed > 0 ? $parsed : null;
    }
}

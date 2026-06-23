<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Catalog;

final class ListPhotosRequest extends CatalogListRequest
{
    /**
     * @return list<string>
     */
    protected function allowedSorts(): array
    {
        return ['title', 'flickr_photo_id', 'owner_nsid', 'id'];
    }
}

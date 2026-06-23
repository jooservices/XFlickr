<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Catalog;

final class ListPhotosetsRequest extends CatalogListRequest
{
    /**
     * @return list<string>
     */
    protected function allowedSorts(): array
    {
        return ['title', 'photo_count', 'owner_nsid', 'flickr_photoset_id', 'id'];
    }
}

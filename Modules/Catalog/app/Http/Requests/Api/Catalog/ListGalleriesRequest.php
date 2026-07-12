<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests\Api\Catalog;

final class ListGalleriesRequest extends CatalogListRequest
{
    /**
     * @return list<string>
     */
    protected function allowedSorts(): array
    {
        return ['title', 'photo_count', 'owner_nsid', 'flickr_gallery_id', 'id'];
    }
}

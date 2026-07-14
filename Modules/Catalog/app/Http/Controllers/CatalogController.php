<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Catalog\Services\CatalogQueryService;
use Modules\Crawler\Models\Connection;
use Modules\Flickr\Services\FlickrAccountsService;
use Modules\Flickr\Support\ConnectionPresenter;

final class CatalogController
{
    public function __construct(
        private readonly FlickrAccountsService $oauth,
        private readonly CatalogQueryService $catalog,
    ) {}

    public function photos(?Connection $connection = null): Response
    {
        return Inertia::render('Catalog/Photos', [
            'account' => $this->presentAccount($connection),
        ]);
    }

    public function photosets(?Connection $connection = null): Response
    {
        return Inertia::render('Catalog/Photosets', [
            'account' => $this->presentAccount($connection),
        ]);
    }

    public function showPhotoset(?Connection $connection, int $photoset): Response
    {
        $presented = $this->catalog->photoset($photoset);

        if ($presented === null) {
            abort(404);
        }

        return Inertia::render('Catalog/Photosets/Show', [
            'account' => $this->presentAccount($connection),
            'photoset' => $presented,
        ]);
    }

    public function galleries(?Connection $connection = null): Response
    {
        return Inertia::render('Catalog/Galleries', [
            'account' => $this->presentAccount($connection),
        ]);
    }

    public function favorites(?Connection $connection = null): Response
    {
        return Inertia::render('Catalog/Favorites', [
            'account' => $this->presentAccount($connection),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentAccount(?Connection $connection): ?array
    {
        $connection ??= $this->oauth->activeConnection();

        return $connection !== null ? ConnectionPresenter::toArray($connection) : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Flickr\FlickrOAuthService;
use App\Support\Flickr\ConnectionPresenter;
use Inertia\Inertia;
use Inertia\Response;
use JOOservices\XFlickrCrawler\Models\Connection;

final class CatalogController
{
    public function __construct(
        private readonly FlickrOAuthService $oauth,
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

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Flickr\ConnectionPresenter;
use Inertia\Inertia;
use Inertia\Response;
use JOOservices\XFlickrCrawler\Facades\FlickrService;

final class CrawlOperationsController
{
    public function index(): Response
    {
        $accounts = FlickrService::connections()
            ->list()
            ->map(fn ($connection): array => ConnectionPresenter::toArray($connection));

        return Inertia::render('Crawl/Operations', [
            'accounts' => $accounts,
        ]);
    }
}

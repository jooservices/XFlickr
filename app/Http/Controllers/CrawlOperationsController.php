<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Flickr\FlickrOAuthService;
use App\Support\SpiderRuntimeConfig;
use Inertia\Inertia;
use Inertia\Response;

final class CrawlOperationsController
{
    public function index(FlickrOAuthService $oauth, SpiderRuntimeConfig $spiderConfig): Response
    {
        return Inertia::render('Crawl/Operations', [
            'accounts' => $oauth->listAccounts(),
            'spiderEnabled' => $spiderConfig->enabled(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Flickr\Services\FlickrOAuthService;
use Modules\Spider\Support\SpiderRuntimeConfig;

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

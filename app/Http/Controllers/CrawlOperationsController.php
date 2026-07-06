<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Flickr\FlickrOAuthService;
use Inertia\Inertia;
use Inertia\Response;

final class CrawlOperationsController
{
    public function index(FlickrOAuthService $oauth): Response
    {
        return Inertia::render('Crawl/Operations', [
            'accounts' => $oauth->listAccounts(),
        ]);
    }
}

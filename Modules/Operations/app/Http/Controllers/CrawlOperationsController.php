<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Flickr\Services\FlickrOAuthService;

final class CrawlOperationsController extends Controller
{
    public function __construct(
        private readonly FlickrOAuthService $oauth,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Crawl/Operations', [
            'accounts' => $this->oauth->listAccounts(),
        ]);
    }
}

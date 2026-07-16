<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Flickr\Services\FlickrAccountsService;

final class SyncOperationsController extends Controller
{
    public function __construct(
        private readonly FlickrAccountsService $oauth,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Crawl/Sync', [
            'accounts' => $this->oauth->listAccounts(),
        ]);
    }
}

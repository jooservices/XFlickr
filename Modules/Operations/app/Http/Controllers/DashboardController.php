<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Flickr\Services\FlickrRateLimitPresenter;
use Modules\Operations\Services\DashboardService;

final class DashboardController
{
    public function index(DashboardService $dashboard, FlickrRateLimitPresenter $rateLimit): Response
    {
        return Inertia::render('Dashboard', [
            'snapshot' => $dashboard->snapshot($rateLimit),
        ]);
    }
}

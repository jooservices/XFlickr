<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\Flickr\FlickrRateLimitPresenter;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    public function index(DashboardService $dashboard, FlickrRateLimitPresenter $rateLimit): Response
    {
        return Inertia::render('Dashboard', [
            'snapshot' => $dashboard->snapshot($rateLimit),
        ]);
    }
}

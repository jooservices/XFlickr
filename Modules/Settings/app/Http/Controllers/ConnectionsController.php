<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Flickr\Services\FlickrAccountsService;
use Modules\Settings\Http\Requests\ShowConnectionsRequest;
use Modules\Settings\Services\OnboardingStatusService;
use Modules\Storage\Services\StorageService;

final class ConnectionsController extends Controller
{
    public function index(
        ShowConnectionsRequest $request,
        FlickrAccountsService $flickrOAuth,
        StorageService $storageSettings,
        OnboardingStatusService $onboarding,
    ): Response {
        return Inertia::render('Connections/Index', [
            'provider' => $request->provider(),
            'flickr_accounts' => $flickrOAuth->listAccounts()->values(),
            'flickr_apps' => $flickrOAuth->listAppProfiles(),
            'default_callback_url' => $flickrOAuth->defaultCallbackUrl(),
            'storage_accounts' => $storageSettings->accounts(),
            'storage_apps' => $storageSettings->apps(),
            'storage_redirects' => $storageSettings->redirects(),
            'storage_drivers' => $storageSettings->drivers(),
            'has_completed_crawl' => $onboarding->hasCompletedCrawl(),
        ]);
    }
}

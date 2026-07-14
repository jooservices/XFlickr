<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Flickr\Services\FlickrAppProfileService;
use Modules\Flickr\Services\FlickrOAuthService;
use Modules\Settings\Http\Requests\ShowConnectionsRequest;
use Modules\Storage\Services\StorageSettingsService;

final class ConnectionsController
{
    public function index(
        ShowConnectionsRequest $request,
        FlickrOAuthService $flickrOAuth,
        FlickrAppProfileService $flickrApps,
        StorageSettingsService $storageSettings,
    ): Response {
        return Inertia::render('Connections/Index', [
            'provider' => $request->provider(),
            'flickr_accounts' => $flickrOAuth->listAccounts()->values(),
            'flickr_apps' => $flickrApps->listPublic()->values(),
            'default_callback_url' => $flickrApps->defaultCallbackUrl(),
            'storage_accounts' => $storageSettings->accounts(),
            'storage_apps' => $storageSettings->apps(),
            'storage_redirects' => $storageSettings->redirects(),
            'storage_drivers' => $storageSettings->drivers(),
        ]);
    }
}

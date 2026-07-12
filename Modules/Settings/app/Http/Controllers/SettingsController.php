<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use JOOservices\XFlickrCrawler\Support\XFlickrConfig;
use Modules\Flickr\Services\FlickrAppProfileService;
use Modules\Flickr\Services\FlickrOAuthService;
use Modules\Settings\Http\Requests\ShowSettingsRequest;
use Modules\Settings\Services\RuntimeConfigAdminService;
use Modules\Storage\Services\StorageSettingsService;

final class SettingsController
{
    public function index(
        ShowSettingsRequest $request,
        FlickrOAuthService $flickrOAuth,
        FlickrAppProfileService $appProfiles,
        RuntimeConfigAdminService $runtimeConfig,
        StorageSettingsService $storageSettings,
    ): Response {
        $tab = $request->tab();

        $configPayload = $runtimeConfig->indexPayload();

        return Inertia::render('Settings/Index', [
            'tab' => $tab,
            'runtime_config' => $configPayload,
            'flickr' => [
                'status' => $flickrOAuth->status(),
                'accounts' => $flickrOAuth->listAccounts()->values(),
                'apps' => $appProfiles->listPublic()->values(),
                'default_callback_url' => $appProfiles->defaultCallbackUrl(),
                'default_app_profile' => 'main',
                'settings' => [
                    'default_app_profile' => XFlickrConfig::defaultAppProfile(),
                    'global_pause' => XFlickrConfig::globalPause(),
                ],
            ],
            'storage_accounts' => $storageSettings->accounts(),
            'storage_apps' => $storageSettings->apps(),
            'storage_redirects' => $storageSettings->redirects(),
            'storage_drivers' => $storageSettings->drivers(),
            'runtime_config_available' => app()->bound('config-store'),
        ]);
    }
}

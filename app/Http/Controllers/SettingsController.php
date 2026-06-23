<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StorageDriver;
use App\Http\Requests\Settings\ShowSettingsRequest;
use App\Repositories\StorageAccountRepository;
use App\Services\Flickr\FlickrAppProfileService;
use App\Services\Flickr\FlickrOAuthService;
use App\Services\RuntimeConfigAdminService;
use App\Services\Storage\StorageAppProfileService;
use App\Support\Storage\StorageAccountPresenter;
use Inertia\Inertia;
use Inertia\Response;
use JOOservices\XFlickrCrawler\Support\XFlickrConfig;

final class SettingsController
{
    public function index(
        ShowSettingsRequest $request,
        FlickrOAuthService $flickrOAuth,
        FlickrAppProfileService $appProfiles,
        StorageAppProfileService $storageProfiles,
        RuntimeConfigAdminService $runtimeConfig,
        StorageAccountRepository $storageAccounts,
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
            'storage_accounts' => $storageAccounts->listOrderedForSettings()
                ->map(fn ($account): array => StorageAccountPresenter::toPublicArray($account))
                ->values(),
            'storage_apps' => $storageProfiles->listPublic()->values(),
            'storage_redirects' => $storageProfiles->defaultRedirects(),
            'storage_drivers' => collect(StorageDriver::all())->map(fn (StorageDriver $driver): array => [
                'value' => $driver->value,
                'label' => $driver->label(),
                'requires_oauth' => $driver->requiresOAuth(),
                'requires_app' => $driver->requiresApp(),
                'requires_account' => $driver->requiresAccount(),
            ])->values(),
            'runtime_config_available' => app()->bound('config-store'),
        ]);
    }
}

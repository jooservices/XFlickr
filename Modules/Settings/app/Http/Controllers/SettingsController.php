<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Flickr\Services\FlickrAccountsService;
use Modules\Settings\Http\Requests\ShowSettingsRequest;
use Modules\Settings\Services\RuntimeConfigAdminService;
use Modules\Storage\Services\StorageService;

final class SettingsController
{
    public function index(
        ShowSettingsRequest $request,
        FlickrAccountsService $flickrOAuth,
        RuntimeConfigAdminService $runtimeConfig,
        StorageService $storageSettings,
    ): Response|RedirectResponse {
        $tab = $request->tab();

        if ($tab === 'flickr') {
            return redirect()->route('connections.index', ['provider' => 'flickr']);
        }

        if ($tab === 'storage') {
            return redirect()->route('connections.index', ['provider' => 'storage']);
        }

        $configPayload = $runtimeConfig->indexPayload();

        return Inertia::render('Settings/Index', [
            'tab' => 'general',
            'runtime_config' => $configPayload,
            'has_flickr_accounts' => $flickrOAuth->listAccounts()->isNotEmpty(),
            'has_storage_accounts' => $storageSettings->accounts()->isNotEmpty(),
            'runtime_config_available' => app()->bound('config-store'),
        ]);
    }
}

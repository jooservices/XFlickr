<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Settings\RuntimeConfigPathRequest;
use App\Http\Requests\Settings\StoreRuntimeConfigRequest;
use App\Services\RuntimeConfigAdminService;
use Illuminate\Http\RedirectResponse;

final class RuntimeConfigController
{
    public function store(StoreRuntimeConfigRequest $request, RuntimeConfigAdminService $configAdmin): RedirectResponse
    {
        $validated = $request->validated();

        $configAdmin->upsert($validated);

        return redirect()->route('settings.index', ['tab' => 'general'])->with('success', 'Configuration saved.');
    }

    public function destroy(RuntimeConfigPathRequest $request, RuntimeConfigAdminService $configAdmin): RedirectResponse
    {
        $configAdmin->delete($request->configPath());

        return redirect()->route('settings.index', ['tab' => 'general'])->with('success', 'Configuration deleted.');
    }

    public function reset(RuntimeConfigPathRequest $request, RuntimeConfigAdminService $configAdmin): RedirectResponse
    {
        $configAdmin->resetToDefault($request->configPath());

        return redirect()->route('settings.index', ['tab' => 'general'])->with('success', 'Configuration reset to default.');
    }
}

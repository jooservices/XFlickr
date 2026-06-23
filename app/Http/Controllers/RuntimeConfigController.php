<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

    public function destroy(string $path, RuntimeConfigAdminService $configAdmin): RedirectResponse
    {
        $configAdmin->delete(urldecode($path));

        return redirect()->route('settings.index', ['tab' => 'general'])->with('success', 'Configuration deleted.');
    }

    public function reset(string $path, RuntimeConfigAdminService $configAdmin): RedirectResponse
    {
        $configAdmin->resetToDefault(urldecode($path));

        return redirect()->route('settings.index', ['tab' => 'general'])->with('success', 'Configuration reset to default.');
    }
}

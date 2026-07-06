<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Settings\RuntimeConfigPathRequest;
use App\Http\Requests\Settings\StoreRuntimeConfigRequest;
use App\Services\RuntimeConfigAdminService;
use App\Support\Observability\AdminActionLogger;
use Illuminate\Http\RedirectResponse;

final class RuntimeConfigController
{
    public function store(StoreRuntimeConfigRequest $request, RuntimeConfigAdminService $configAdmin, AdminActionLogger $audit): RedirectResponse
    {
        $validated = $request->validated();

        $configAdmin->upsert($validated);

        $audit->record('settings.runtime_config.saved', [
            'path' => $validated['path'] ?? null,
            'type' => $validated['type'] ?? null,
        ]);

        return redirect()->route('settings.index', ['tab' => 'general'])->with('success', 'Configuration saved.');
    }

    public function destroy(RuntimeConfigPathRequest $request, RuntimeConfigAdminService $configAdmin, AdminActionLogger $audit): RedirectResponse
    {
        $configAdmin->delete($request->configPath());

        $audit->record('settings.runtime_config.deleted', [
            'path' => $request->configPath(),
        ]);

        return redirect()->route('settings.index', ['tab' => 'general'])->with('success', 'Configuration deleted.');
    }

    public function reset(RuntimeConfigPathRequest $request, RuntimeConfigAdminService $configAdmin, AdminActionLogger $audit): RedirectResponse
    {
        $configAdmin->resetToDefault($request->configPath());

        $audit->record('settings.runtime_config.reset', [
            'path' => $request->configPath(),
        ]);

        return redirect()->route('settings.index', ['tab' => 'general'])->with('success', 'Configuration reset to default.');
    }
}

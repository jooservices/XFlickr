<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers;

use App\Support\Observability\AdminActionLogger;
use Illuminate\Http\RedirectResponse;
use Modules\Settings\Http\Requests\RuntimeConfigPathRequest;
use Modules\Settings\Http\Requests\StoreRuntimeConfigRequest;
use Modules\Settings\Http\Requests\ToggleGlobalCrawlPauseRequest;
use Modules\Settings\Http\Requests\UpdateSpiderModeRequest;
use Modules\Settings\Services\RuntimeConfigAdminService;

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

    public function updateCrawlPause(ToggleGlobalCrawlPauseRequest $request, RuntimeConfigAdminService $configAdmin, AdminActionLogger $audit): RedirectResponse
    {
        $paused = $request->paused();

        $configAdmin->setGlobalCrawlPause($paused);

        $audit->record('settings.global_crawl_pause.updated', [
            'paused' => $paused,
        ]);

        return back()->with(
            'success',
            $paused ? 'Global crawl pause enabled.' : 'Global crawl pause cleared.',
        );
    }

    public function updateSpiderMode(UpdateSpiderModeRequest $request, RuntimeConfigAdminService $configAdmin, AdminActionLogger $audit): RedirectResponse
    {
        $settings = $request->spiderSettings();

        $configAdmin->updateSpiderMode($settings);

        $audit->record('settings.spider_mode.updated', $settings);

        return back()->with(
            'success',
            $settings['enabled']
                ? 'Spider mode enabled; global crawl pause cleared.'
                : 'Spider mode disabled.',
        );
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

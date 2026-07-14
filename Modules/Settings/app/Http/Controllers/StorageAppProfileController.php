<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers;

use App\Support\Observability\AdminActionLogger;
use Illuminate\Http\RedirectResponse;
use Modules\Settings\Http\Requests\StoreStorageAppProfileRequest;
use Modules\Storage\Services\StorageAppProfileService;

final class StorageAppProfileController
{
    public function store(StoreStorageAppProfileRequest $request, StorageAppProfileService $profiles, AdminActionLogger $audit): RedirectResponse
    {
        $validated = $request->validated();

        $profiles->save($validated);

        $audit->record('settings.storage_app.saved', [
            'provider' => $validated['provider'] ?? null,
        ]);

        return redirect()
            ->route('connections.index', ['provider' => 'storage'])
            ->with('success', 'Storage app credentials saved.');
    }
}

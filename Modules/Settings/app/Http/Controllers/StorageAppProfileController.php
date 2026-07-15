<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers;

use App\Support\Observability\AdminActionLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Settings\Http\Requests\StoreStorageAppProfileRequest;
use Modules\Storage\Services\StorageService;

final class StorageAppProfileController extends Controller
{
    public function store(StoreStorageAppProfileRequest $request, StorageService $profiles, AdminActionLogger $audit): RedirectResponse
    {
        $dto = $request->toDto();

        $profiles->saveAppProfile($dto);

        $audit->record('settings.storage_app.saved', [
            'provider' => $dto->provider,
        ]);

        return redirect()
            ->route('connections.index', ['provider' => 'storage'])
            ->with('success', 'Storage app credentials saved.');
    }
}

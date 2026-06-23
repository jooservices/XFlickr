<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Settings\StoreStorageAppProfileRequest;
use App\Services\Storage\StorageAppProfileService;
use Illuminate\Http\RedirectResponse;

final class StorageAppProfileController
{
    public function store(StoreStorageAppProfileRequest $request, StorageAppProfileService $profiles): RedirectResponse
    {
        $validated = $request->validated();

        $profiles->save($validated);

        return redirect()
            ->route('settings.index', ['tab' => 'storage'])
            ->with('success', 'Storage app credentials saved.');
    }
}

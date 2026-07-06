<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Settings\StoreFlickrAppProfileRequest;
use App\Services\Flickr\FlickrAppProfileService;
use App\Support\Observability\AdminActionLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use JOOservices\XFlickrCrawler\Exceptions\FlickrAppNotConfiguredException;

final class FlickrAppProfileController
{
    public function store(StoreFlickrAppProfileRequest $request, FlickrAppProfileService $profiles, AdminActionLogger $audit): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $profiles->save($validated);
        } catch (FlickrAppNotConfiguredException $exception) {
            throw ValidationException::withMessages([
                'profile' => $exception->getMessage(),
            ]);
        }

        $audit->record('settings.flickr_app.saved', [
            'profile' => $validated['profile'] ?? 'main',
        ]);

        return redirect()
            ->route('settings.index', ['tab' => 'flickr'])
            ->with('success', 'Flickr app credentials saved.');
    }
}

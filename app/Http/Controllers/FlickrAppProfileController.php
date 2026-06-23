<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Settings\StoreFlickrAppProfileRequest;
use App\Services\Flickr\FlickrAppProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use JOOservices\XFlickrCrawler\Exceptions\FlickrAppNotConfiguredException;

final class FlickrAppProfileController
{
    public function store(StoreFlickrAppProfileRequest $request, FlickrAppProfileService $profiles): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $profiles->save($validated);
        } catch (FlickrAppNotConfiguredException $exception) {
            throw ValidationException::withMessages([
                'profile' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('settings.index', ['tab' => 'flickr'])
            ->with('success', 'Flickr app credentials saved.');
    }
}

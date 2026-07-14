<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers;

use App\Support\Observability\AdminActionLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Modules\Crawler\Exceptions\FlickrAppNotConfiguredException;
use Modules\Flickr\Services\FlickrAccountsService;
use Modules\Settings\Http\Requests\DestroyFlickrAppProfileRequest;
use Modules\Settings\Http\Requests\StoreFlickrAppProfileRequest;

final class FlickrAppProfileController
{
    public function store(StoreFlickrAppProfileRequest $request, FlickrAccountsService $profiles, AdminActionLogger $audit): RedirectResponse
    {
        $dto = $request->toDto();

        try {
            $profiles->saveAppProfile($dto);
        } catch (FlickrAppNotConfiguredException $exception) {
            throw ValidationException::withMessages([
                'profile' => $exception->getMessage(),
            ]);
        }

        $audit->record('settings.flickr_app.saved', [
            'profile' => $dto->profile,
        ]);

        return redirect()
            ->route('connections.index', ['provider' => 'flickr'])
            ->with('success', 'Flickr app credentials saved.');
    }

    public function destroy(DestroyFlickrAppProfileRequest $request, FlickrAccountsService $profiles, AdminActionLogger $audit): RedirectResponse
    {
        try {
            $profile = $profiles->deleteAppProfile($request->profile());
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return redirect()
                ->route('connections.index', ['provider' => 'flickr'])
                ->with('error', is_string($message) ? $message : 'Flickr app profile could not be deleted.');
        }

        $audit->record('settings.flickr_app.deleted', [
            'profile' => $profile,
        ]);

        return redirect()
            ->route('connections.index', ['provider' => 'flickr'])
            ->with('success', 'Flickr app profile deleted.');
    }
}

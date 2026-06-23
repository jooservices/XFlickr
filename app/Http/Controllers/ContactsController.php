<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Flickr\FlickrOAuthService;
use Illuminate\Http\RedirectResponse;

final class ContactsController
{
    public function index(FlickrOAuthService $oauth): RedirectResponse
    {
        $connection = $oauth->activeConnection();
        if ($connection === null) {
            return redirect()
                ->route('settings.index', ['tab' => 'flickr'])
                ->with('error', 'Connect a Flickr account before viewing contacts.');
        }

        return redirect()->route('flickr.accounts.contacts.index', $connection);
    }
}

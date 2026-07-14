<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Modules\Flickr\Services\FlickrAccountsService;

final class ContactsController
{
    public function index(FlickrAccountsService $oauth): RedirectResponse
    {
        $connection = $oauth->activeConnection();
        if ($connection === null) {
            return redirect()
                ->route('connections.index', ['provider' => 'flickr'])
                ->with('error', 'Connect a Flickr account before viewing contacts.');
        }

        return redirect()->route('flickr.accounts.contacts.index', $connection);
    }
}

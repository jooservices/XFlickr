<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use JOOservices\XFlickrCrawler\Models\Connection;
use Modules\Contacts\Services\ContactFullPassPlannerService;
use Modules\Spider\Http\Requests\SpiderConnectionRequest;
use RuntimeException;

final class ContactFullPassController
{
    public function start(SpiderConnectionRequest $request, Connection $connection, ContactFullPassPlannerService $planner): RedirectResponse
    {
        try {
            $planner->start($connection);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Full contact pass started for this account.');
    }

    public function stop(SpiderConnectionRequest $request, Connection $connection, ContactFullPassPlannerService $planner): RedirectResponse
    {
        $run = $planner->stop($connection);

        if ($run === null) {
            return back()->with('error', 'No active full contact pass for this account.');
        }

        return back()->with('success', 'Full contact pass paused.');
    }
}

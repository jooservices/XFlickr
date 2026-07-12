<?php

declare(strict_types=1);

namespace Modules\Spider\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use JOOservices\XFlickrCrawler\Models\Connection;
use Modules\Spider\Http\Requests\SpiderConnectionRequest;
use Modules\Spider\Services\SpiderPlannerService;
use RuntimeException;

final class SpiderController
{
    public function start(SpiderConnectionRequest $request, Connection $connection, SpiderPlannerService $planner): RedirectResponse
    {
        try {
            $planner->start($connection);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Spider run started for this account.');
    }

    public function stop(SpiderConnectionRequest $request, Connection $connection, SpiderPlannerService $planner): RedirectResponse
    {
        $run = $planner->stop($connection);

        if ($run === null) {
            return back()->with('error', 'No active spider run for this account.');
        }

        return back()->with('success', 'Spider run paused.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Flickr\SpiderConnectionRequest;
use App\Services\Flickr\SpiderPlannerService;
use Illuminate\Http\RedirectResponse;
use JOOservices\XFlickrCrawler\Models\Connection;
use RuntimeException;

final class SpiderController
{
    public function start(SpiderConnectionRequest $request, Connection $connection, SpiderPlannerService $planner): RedirectResponse
    {
        try {
            $planner->start($connection);
        } catch (RuntimeException $exception) {
            return redirect()->route('crawl.operations')->with('error', $exception->getMessage());
        }

        return redirect()->route('crawl.operations')->with('success', 'Spider run started for this account.');
    }

    public function stop(SpiderConnectionRequest $request, Connection $connection, SpiderPlannerService $planner): RedirectResponse
    {
        $run = $planner->stop($connection);

        if ($run === null) {
            return redirect()->route('crawl.operations')->with('error', 'No active spider run for this account.');
        }

        return redirect()->route('crawl.operations')->with('success', 'Spider run paused.');
    }
}

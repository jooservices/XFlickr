<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crawler\Models\Connection;
use Modules\Flickr\Exceptions\FlickrTokenInvalidException;
use Modules\Flickr\Exceptions\GlobalCrawlPauseException;
use Modules\Flickr\Http\Requests\CrawlFlickrAccountRequest;
use Modules\Flickr\Http\Requests\ShowFlickrAccountsRequest;
use Modules\Flickr\Services\FlickrCrawlService;
use Modules\Flickr\Support\ConnectionPresenter;

final class FlickrAccountController
{
    public function show(Connection $connection): Response
    {
        return Inertia::render('Flickr/Show', [
            'account' => ConnectionPresenter::toArray($connection),
        ]);
    }

    public function list(ShowFlickrAccountsRequest $request): RedirectResponse
    {
        return redirect()->route('connections.index', [
            'provider' => 'flickr',
        ]);
    }

    public function crawl(CrawlFlickrAccountRequest $request, Connection $connection, FlickrCrawlService $crawlService): RedirectResponse
    {
        try {
            $crawlService->crawlMany(
                $connection,
                $request->crawlTypes(),
                $request->subjectNsid(),
            );
        } catch (FlickrTokenInvalidException|GlobalCrawlPauseException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Crawl started.');
    }
}

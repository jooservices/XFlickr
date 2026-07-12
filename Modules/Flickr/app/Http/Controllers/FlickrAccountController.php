<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use JOOservices\XFlickrCrawler\Models\Connection;
use Modules\Flickr\Exceptions\FlickrTokenInvalidException;
use Modules\Flickr\Http\Requests\CrawlFlickrAccountRequest;
use Modules\Flickr\Services\FlickrCrawlService;
use Modules\Flickr\Services\FlickrOAuthService;
use Modules\Flickr\Support\ConnectionPresenter;

final class FlickrAccountController
{
    public function show(Connection $connection): Response
    {
        return Inertia::render('Flickr/Show', [
            'account' => ConnectionPresenter::toArray($connection),
        ]);
    }

    public function list(FlickrOAuthService $oauth): Response
    {
        return Inertia::render('Flickr/Index', [
            'accounts' => $oauth->listAccounts(),
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
        } catch (FlickrTokenInvalidException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Crawl started.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Flickr\CrawlFlickrAccountRequest;
use App\Services\Flickr\FlickrCrawlService;
use App\Support\Flickr\ConnectionPresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use JOOservices\XFlickrCrawler\Facades\FlickrService;
use JOOservices\XFlickrCrawler\Models\Connection;

final class FlickrAccountController
{
    public function index(Connection $connection): RedirectResponse
    {
        return redirect()->route('flickr.accounts.contacts.index', $connection);
    }

    public function list(): Response
    {
        $accounts = FlickrService::connections()
            ->list()
            ->map(fn (Connection $connection): array => ConnectionPresenter::toArray($connection));

        return Inertia::render('Flickr/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function crawl(CrawlFlickrAccountRequest $request, Connection $connection, FlickrCrawlService $crawlService): RedirectResponse
    {
        $crawlService->crawlMany(
            $connection,
            $request->crawlTypes(),
            $request->subjectNsid(),
        );

        return back()->with('success', 'Crawl started.');
    }
}

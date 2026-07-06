<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Flickr\CrawlFlickrContactBulkRequest;
use App\Http\Requests\Flickr\CrawlFlickrContactRequest;
use App\Http\Requests\Flickr\FlickrContactProgressRequest;
use App\Http\Requests\Flickr\FlickrContactSuggestRequest;
use App\Http\Requests\Flickr\ListFlickrContactsRequest;
use App\Services\Flickr\ContactDetailService;
use App\Services\Flickr\ContactListPresenter;
use App\Services\Flickr\ContactListQueryService;
use App\Services\Flickr\ContactListSorter;
use App\Services\Flickr\FlickrCrawlService;
use App\Support\Flickr\ConnectionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use JOOservices\XFlickrCrawler\Models\Connection;

final class FlickrContactController
{
    public function index(
        ListFlickrContactsRequest $request,
        Connection $connection,
        ContactListPresenter $presenter,
        ContactListQueryService $contactList,
    ): Response {
        $paginator = $contactList->paginateForConnection(
            $connection,
            $request->search(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->page(),
        );
        $contacts = $presenter->present($connection, $paginator->items());

        return Inertia::render('Contacts/Index', [
            'account' => ConnectionPresenter::toArray($connection),
            'contacts' => $contacts,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'search' => $request->search(),
                'sort' => in_array($request->rawSort(), ContactListSorter::SORTABLE_COLUMNS, true) ? $request->rawSort() : 'username',
                'direction' => strtolower($request->rawDirection()) === 'desc' ? 'desc' : 'asc',
            ],
        ]);
    }

    public function progress(
        FlickrContactProgressRequest $request,
        Connection $connection,
        ContactListPresenter $presenter,
        ContactListQueryService $contactList,
    ): JsonResponse {
        $nsids = $request->nsidList();

        if ($nsids === []) {
            return response()->json(['contacts' => []]);
        }

        $contacts = $contactList->listByNsids($connection, $nsids);

        return response()->json([
            'contacts' => $presenter->present($connection, $contacts),
        ]);
    }

    public function suggest(
        FlickrContactSuggestRequest $request,
        Connection $connection,
        ContactListQueryService $contactList,
    ): JsonResponse {
        if (! $request->isSearchable()) {
            return response()->json([]);
        }

        return response()->json($contactList->suggest($connection, $request->search(), $request->limit()));
    }

    public function show(
        Connection $connection,
        string $contactNsid,
        ContactDetailService $contacts,
    ): Response {
        $payload = $contacts->forShow($connection, $contactNsid);
        abort_unless($payload !== null, 404);

        return Inertia::render('Contacts/Show', $payload);
    }

    public function crawlBulk(CrawlFlickrContactBulkRequest $request, Connection $connection, FlickrCrawlService $crawlService): RedirectResponse
    {
        $contactNsids = $request->contactNsids();
        if ($contactNsids === []) {
            return back()->with('error', 'No contacts selected.');
        }

        $crawlTypes = $request->crawlTypes();

        foreach ($contactNsids as $contactNsid) {
            $crawlService->crawlMany($connection, $crawlTypes, $contactNsid);
        }

        $count = count($contactNsids);

        return back()->with('success', "Contact crawl started for {$count} contact(s).");
    }

    public function crawl(CrawlFlickrContactRequest $request, Connection $connection, string $contactNsid, FlickrCrawlService $crawlService): RedirectResponse
    {
        $crawlService->crawlMany($connection, $request->crawlTypes(), $contactNsid);

        return back()->with('success', 'Contact crawl started.');
    }
}

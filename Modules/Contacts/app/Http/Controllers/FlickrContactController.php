<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Contacts\Http\Requests\CrawlFlickrContactBulkRequest;
use Modules\Contacts\Http\Requests\CrawlFlickrContactRequest;
use Modules\Contacts\Http\Requests\ListFlickrContactsRequest;
use Modules\Contacts\Services\ContactCrawlStateService;
use Modules\Contacts\Services\ContactListPresenter;
use Modules\Contacts\Services\ContactListQueryService;
use Modules\Contacts\Services\ContactListSorter;
use Modules\Contacts\Services\ContactStatsService;
use Modules\Crawler\Models\Connection;
use Modules\Flickr\Exceptions\FlickrTokenInvalidException;
use Modules\Flickr\Exceptions\GlobalCrawlPauseException;
use Modules\Flickr\Services\FlickrAccountsService;
use Modules\Flickr\Support\ConnectionPresenter;
use Modules\Flickr\Support\ContactPresenter;

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
            $request->starredOnly(),
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
                'starred_only' => $request->starredOnly(),
                'view' => $request->viewMode(),
            ],
        ]);
    }

    public function show(
        Connection $connection,
        string $contactNsid,
        ContactListQueryService $contactList,
        ContactStatsService $stats,
        ContactCrawlStateService $crawlState,
    ): Response {
        abort_unless($contactList->isLinked($connection, $contactNsid), 404);

        $contact = $contactList->findContact($contactNsid);
        $counts = $stats->catalogCountsFor($connection, [$contactNsid])[$contactNsid] ?? [
            'photos' => 0,
            'photosets' => 0,
            'galleries' => 0,
            'favorites' => 0,
        ];

        return Inertia::render('Contacts/Show', [
            'account' => ConnectionPresenter::toArray($connection),
            'contact' => ContactPresenter::toDetailArray($contact),
            'catalog_stats' => $stats->detailStatsFor($connection, $contactNsid),
            'crawl_state' => $crawlState->forContact($connection, $contactNsid, [$contactNsid => $counts]),
        ]);
    }

    public function crawlBulk(
        CrawlFlickrContactBulkRequest $request,
        Connection $connection,
        FlickrAccountsService $crawlService,
        ContactListQueryService $contactList,
    ): RedirectResponse {
        $contactNsids = $request->wantsSelectAll()
            ? $contactList->listNsidsForConnection(
                $connection,
                $request->bulkSearch(),
                $request->bulkStarredOnly(),
            )
            : $request->contactNsids();

        if ($contactNsids === []) {
            return back()->with('error', 'No contacts selected.');
        }

        $crawlTypes = $request->crawlTypes();

        try {
            foreach ($contactNsids as $contactNsid) {
                $crawlService->crawlMany($connection, $crawlTypes, $contactNsid);
            }
        } catch (FlickrTokenInvalidException|GlobalCrawlPauseException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $count = count($contactNsids);

        return back()->with('success', "Contact crawl started for {$count} contact(s).");
    }

    public function crawl(CrawlFlickrContactRequest $request, Connection $connection, string $contactNsid, FlickrAccountsService $crawlService): RedirectResponse
    {
        try {
            $crawlService->crawlMany($connection, $request->crawlTypes(), $contactNsid);
        } catch (FlickrTokenInvalidException|GlobalCrawlPauseException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Contact crawl started.');
    }
}

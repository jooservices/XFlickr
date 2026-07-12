<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use JOOservices\XFlickrCrawler\Models\Connection;
use Modules\Contacts\Http\Requests\CrawlFlickrContactRequest;
use Modules\Contacts\Http\Requests\FlickrContactProgressRequest;
use Modules\Contacts\Http\Requests\FlickrContactSuggestRequest;
use Modules\Contacts\Http\Resources\ContactCrawlRunAcceptedResource;
use Modules\Contacts\Http\Resources\ContactProgressResource;
use Modules\Contacts\Http\Resources\ContactSuggestionResource;
use Modules\Contacts\Services\ContactListPresenter;
use Modules\Contacts\Services\ContactListQueryService;
use Modules\Flickr\Exceptions\FlickrTokenInvalidException;
use Modules\Flickr\Services\FlickrCrawlService;

final class ContactController extends BaseApiController
{
    public function progress(
        FlickrContactProgressRequest $request,
        Connection $connection,
        ContactListPresenter $presenter,
        ContactListQueryService $contactList,
    ): JsonResponse {
        $nsids = $request->nsidList();

        if ($nsids === []) {
            return $this->success(ContactProgressResource::make(['contacts' => []]));
        }

        $contacts = $contactList->listByNsids($connection, $nsids);

        return $this->success(ContactProgressResource::make([
            'contacts' => $presenter->present($connection, $contacts),
        ]));
    }

    public function suggest(
        FlickrContactSuggestRequest $request,
        Connection $connection,
        ContactListQueryService $contactList,
    ): JsonResponse {
        if (! $request->isSearchable()) {
            return $this->success([]);
        }

        return $this->success(ContactSuggestionResource::collection(
            $contactList->suggest($connection, $request->search(), $request->limit()),
        ));
    }

    public function storeCrawlRun(
        CrawlFlickrContactRequest $request,
        Connection $connection,
        string $contactNsid,
        FlickrCrawlService $crawlService,
    ): JsonResponse {
        try {
            $crawlService->crawlMany($connection, $request->crawlTypes(), $contactNsid);
        } catch (FlickrTokenInvalidException $exception) {
            return $this->unprocessable($exception->getMessage());
        }

        return $this->accepted(ContactCrawlRunAcceptedResource::make([]), 'Contact crawl started.');
    }
}

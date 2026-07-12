<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use JOOservices\XFlickrCrawler\Models\Connection;
use Modules\Flickr\Exceptions\FlickrTokenInvalidException;
use Modules\Flickr\Http\Requests\CrawlFlickrAccountRequest;
use Modules\Flickr\Http\Resources\CrawlRunAcceptedResource;
use Modules\Flickr\Http\Resources\FlickrAccountResource;
use Modules\Flickr\Services\FlickrCrawlService;
use Modules\Flickr\Support\ConnectionPresenter;

final class FlickrAccountController extends BaseApiController
{
    public function show(Connection $connection): JsonResponse
    {
        return $this->success([
            'account' => FlickrAccountResource::make(ConnectionPresenter::toArray($connection)),
        ]);
    }

    public function storeCrawlRun(
        CrawlFlickrAccountRequest $request,
        Connection $connection,
        FlickrCrawlService $crawlService,
    ): JsonResponse {
        try {
            $crawlService->crawlMany(
                $connection,
                $request->crawlTypes(),
                $request->subjectNsid(),
            );
        } catch (FlickrTokenInvalidException $exception) {
            return $this->unprocessable($exception->getMessage());
        }

        return $this->accepted(CrawlRunAcceptedResource::make([]), 'Crawl started.');
    }
}

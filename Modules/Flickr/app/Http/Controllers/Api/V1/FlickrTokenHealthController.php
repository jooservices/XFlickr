<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use JOOservices\XFlickrCrawler\Models\Connection;
use Modules\Flickr\Http\Resources\FlickrTokenHealthResource;
use Modules\Flickr\Services\FlickrTokenHealthService;

final class FlickrTokenHealthController extends BaseApiController
{
    public function show(Connection $connection, FlickrTokenHealthService $tokenHealth): JsonResponse
    {
        if ($connection->disconnected_at !== null || $connection->token_payload === '') {
            return $this->success(FlickrTokenHealthResource::make(['token_valid' => null]));
        }

        $result = $tokenHealth->probe($connection, useCache: true);

        return $this->success(FlickrTokenHealthResource::make($result));
    }
}

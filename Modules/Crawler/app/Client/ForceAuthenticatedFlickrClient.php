<?php

declare(strict_types=1);

namespace Modules\Crawler\Client;

use JOOservices\Flickr\Contracts\Client\FlickrClientContract;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\Flickr\DTO\Common\RequestOptionsData;

/**
 * Forces OAuth on every REST call. Intentionally anonymous callers must use a
 * plain FlickrFactory client (no connection token wrap) instead of opting out here.
 */
final class ForceAuthenticatedFlickrClient implements FlickrClientContract
{
    public function __construct(
        private readonly FlickrClientContract $inner,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function call(string $method, array $parameters = [], ?RequestOptionsData $options = null): ApiResponseData
    {
        $options ??= new RequestOptionsData;

        return $this->inner->call($method, $parameters, new RequestOptionsData(
            authenticated: true,
            cache: $options->cache,
            cacheTtl: $options->cacheTtl,
            throwOnApiError: $options->throwOnApiError,
        ));
    }
}

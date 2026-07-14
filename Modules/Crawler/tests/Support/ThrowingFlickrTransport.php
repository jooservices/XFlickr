<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Support;

use JOOservices\Flickr\Contracts\Client\FlickrTransportContract;
use JOOservices\Flickr\DTO\Common\RawResponseData;
use RuntimeException;

final class ThrowingFlickrTransport implements FlickrTransportContract
{
    public function __construct(
        private readonly string $message = 'Transport failure',
    ) {}

    public function request(string $method, string $url, array $options = []): RawResponseData
    {
        throw new RuntimeException($this->message);
    }
}

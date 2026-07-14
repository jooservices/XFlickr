<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Support;

use JOOservices\Flickr\Contracts\Client\FlickrTransportContract;
use JOOservices\Flickr\DTO\Common\RawResponseData;
use RuntimeException;

final class SequencedFlickrTransport implements FlickrTransportContract
{
    public function __construct(
        private readonly FlickrTransportContract $inner,
        private readonly int $throwAfterSuccesses,
        private readonly string $message = 'Transport failure',
    ) {}

    private int $successCount = 0;

    public function request(string $method, string $url, array $options = []): RawResponseData
    {
        if ($this->successCount >= $this->throwAfterSuccesses) {
            throw new RuntimeException($this->message);
        }

        $response = $this->inner->request($method, $url, $options);
        $this->successCount++;

        return $response;
    }
}

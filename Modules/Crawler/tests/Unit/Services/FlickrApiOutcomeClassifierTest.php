<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Services;

use JOOservices\Flickr\DTO\Common\ApiErrorData;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\Flickr\Exceptions\TransportException;
use Modules\Crawler\Enums\ApiOutcome;
use Modules\Crawler\Services\FlickrApiOutcomeClassifier;
use Modules\Crawler\Tests\TestCase;

final class FlickrApiOutcomeClassifierTest extends TestCase
{
    private FlickrApiOutcomeClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new FlickrApiOutcomeClassifier;
    }

    public function test_success_response(): void
    {
        $outcome = $this->classifier->fromApiResponse(new ApiResponseData(ok: true, data: []));

        $this->assertSame(ApiOutcome::Success, $outcome);
    }

    public function test_rate_limited_by_message(): void
    {
        $response = new ApiResponseData(
            ok: false,
            data: [],
            error: new ApiErrorData(code: 105, message: 'Rate limit exceeded'),
        );

        $this->assertSame(ApiOutcome::RateLimited, $this->classifier->fromApiResponse($response));
    }

    public function test_insufficient_permissions_code_99_is_api_error(): void
    {
        $response = new ApiResponseData(
            ok: false,
            data: [],
            error: new ApiErrorData(code: 99, message: 'Insufficient permissions'),
        );

        $this->assertSame(ApiOutcome::ApiError, $this->classifier->fromApiResponse($response));
    }

    public function test_invalid_auth_token_code_98_is_api_error(): void
    {
        $response = new ApiResponseData(
            ok: false,
            data: [],
            error: new ApiErrorData(code: 98, message: 'Invalid auth token'),
        );

        $this->assertSame(ApiOutcome::ApiError, $this->classifier->fromApiResponse($response));
    }

    public function test_api_error(): void
    {
        $response = new ApiResponseData(
            ok: false,
            data: [],
            error: new ApiErrorData(code: 1, message: 'Invalid signature'),
        );

        $this->assertSame(ApiOutcome::ApiError, $this->classifier->fromApiResponse($response));
    }

    public function test_transport_rate_limit(): void
    {
        $outcome = $this->classifier->fromThrowable(new TransportException('HTTP 429 Too Many Requests'));

        $this->assertSame(ApiOutcome::RateLimited, $outcome);
    }

    public function test_transport_error(): void
    {
        $outcome = $this->classifier->fromThrowable(new TransportException('Connection reset'));

        $this->assertSame(ApiOutcome::TransportError, $outcome);
    }
}

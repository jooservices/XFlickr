<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\Flickr\Exceptions\TransportException;
use Modules\Crawler\Enums\ApiOutcome;
use Throwable;

final class FlickrApiOutcomeClassifier
{
    public function fromApiResponse(ApiResponseData $response): ApiOutcome
    {
        if ($response->ok) {
            return ApiOutcome::Success;
        }

        $message = strtolower($response->error !== null ? $response->error->message : '');

        if (str_contains($message, 'rate limit') || str_contains($message, 'too many requests')) {
            return ApiOutcome::RateLimited;
        }

        return ApiOutcome::ApiError;
    }

    public function fromThrowable(Throwable $throwable): ApiOutcome
    {
        if ($throwable instanceof TransportException) {
            $message = strtolower($throwable->getMessage());
            if (str_contains($message, '429') || str_contains($message, 'too many requests')) {
                return ApiOutcome::RateLimited;
            }

            return ApiOutcome::TransportError;
        }

        return ApiOutcome::TransportError;
    }
}

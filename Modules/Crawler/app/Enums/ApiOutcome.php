<?php

declare(strict_types=1);

namespace Modules\Crawler\Enums;

enum ApiOutcome: string
{
    case Success = 'success';
    case RateLimited = 'rate_limited';
    case ApiError = 'api_error';
    case TransportError = 'transport_error';
}

<?php

declare(strict_types=1);

namespace Modules\Storage\Support;

use App\Support\ThirdPartyApiLogger;
use Illuminate\Http\Client\Response;
use Throwable;

/** Thin Storage-domain wrapper over {@see ThirdPartyApiLogger}. */
final class StorageApiLogger
{
    public function __construct(
        private readonly ThirdPartyApiLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function logRequest(
        string $provider,
        string $method,
        string $endpoint,
        float $startedAt,
        ?Response $response = null,
        ?Throwable $exception = null,
        array $context = [],
    ): void {
        $this->logger->logRequest(
            $provider,
            $method,
            $endpoint,
            $startedAt,
            $response,
            $exception,
            $context,
        );
    }
}

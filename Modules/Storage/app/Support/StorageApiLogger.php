<?php

declare(strict_types=1);

namespace Modules\Storage\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

final class StorageApiLogger
{
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
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $payload = [
            'provider' => $provider,
            'method' => strtoupper($method),
            'endpoint' => $endpoint,
            'duration_ms' => $durationMs,
            ...$this->safeContext($context),
        ];

        if ($exception !== null) {
            Log::warning('Storage API call failed.', [
                ...$payload,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if ($response === null) {
            Log::info('Storage API call completed.', $payload);

            return;
        }

        $payload['status'] = $response->status();

        if ($response->successful()) {
            Log::info('Storage API call succeeded.', $payload);

            return;
        }

        $body = $response->body();
        $payload['response_body'] = mb_substr($body, 0, 500);

        Log::warning('Storage API call returned non-2xx.', $payload);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function safeContext(array $context): array
    {
        unset($context['access_token'], $context['token'], $context['authorization'], $context['credentials']);

        return $context;
    }
}

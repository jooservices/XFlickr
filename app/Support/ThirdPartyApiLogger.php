<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Greppable third-party HTTP / OAuth I/O logging. Never pass raw secrets in $context —
 * use {@see self::fingerprint()} (≤12 hex chars) for tokens/keys.
 */
final class ThirdPartyApiLogger
{
    private const int FINGERPRINT_HEX_LENGTH = 12;

    /** @var list<string> */
    private const array SENSITIVE_CONTEXT_KEYS = [
        'access_token',
        'token',
        'authorization',
        'credentials',
        'client_secret',
        'refresh_token',
        'oauth_token',
        'oauth_token_secret',
        'oauth_verifier',
        'upload_token',
        'password',
        'token_payload',
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public function logRequest(
        string $provider,
        string $method,
        string $url,
        float $startedAt,
        ?Response $response = null,
        ?Throwable $exception = null,
        array $context = [],
    ): void {
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $payload = [
            'provider' => $provider,
            'method' => strtoupper($method),
            'url' => $url,
            'duration_ms' => $durationMs,
            ...$this->safeContext($context),
        ];

        if ($exception !== null) {
            Log::warning('Third-party API call failed.', [
                ...$payload,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if ($response === null) {
            Log::info('Third-party API call completed.', $payload);

            return;
        }

        $payload['status'] = $response->status();

        if ($response->successful()) {
            Log::info('Third-party API call succeeded.', $payload);

            return;
        }

        $payload['response_body'] = mb_substr($response->body(), 0, 500);

        Log::warning('Third-party API call returned non-2xx.', $payload);
    }

    /**
     * Stable short fingerprint for secrets/identifiers — never log the raw value.
     */
    public static function fingerprint(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr(hash('sha256', $value), 0, self::FINGERPRINT_HEX_LENGTH);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function safeContext(array $context): array
    {
        foreach (self::SENSITIVE_CONTEXT_KEYS as $key) {
            unset($context[$key]);
        }

        return $context;
    }
}

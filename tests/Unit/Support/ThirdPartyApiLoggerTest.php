<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ThirdPartyApiLogger;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

final class ThirdPartyApiLoggerTest extends TestCase
{
    public function test_fingerprint_is_twelve_hex_chars(): void
    {
        $fp = ThirdPartyApiLogger::fingerprint('secret-token');

        $this->assertNotNull($fp);
        $this->assertSame(12, strlen($fp));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{12}$/', $fp);
        $this->assertSame($fp, ThirdPartyApiLogger::fingerprint('secret-token'));
        $this->assertNull(ThirdPartyApiLogger::fingerprint(''));
        $this->assertNull(ThirdPartyApiLogger::fingerprint(null));
    }

    public function test_safe_context_strips_sensitive_keys(): void
    {
        $safe = (new ThirdPartyApiLogger)->safeContext([
            'account_id' => 7,
            'access_token' => 'raw',
            'upload_token' => 'raw',
            'oauth_token' => 'raw',
            'upload_token_present' => true,
        ]);

        $this->assertSame([
            'account_id' => 7,
            'upload_token_present' => true,
        ], $safe);
    }

    public function test_log_request_success_does_not_include_stripped_secrets(): void
    {
        Log::spy();
        Http::fake([
            'https://example.test/*' => Http::response('ok', 200),
        ]);

        /** @var Response $response */
        $response = Http::get('https://example.test/ping');

        (new ThirdPartyApiLogger)->logRequest(
            'example',
            'GET',
            'https://example.test/ping',
            microtime(true) - 0.01,
            $response,
            null,
            ['access_token' => 'should-not-appear', 'account_id' => 3],
        );

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $serialized = json_encode($context);

                return $message === 'Third-party API call succeeded.'
                    && is_string($serialized)
                    && str_contains($serialized, '"account_id":3')
                    && ! str_contains($serialized, 'should-not-appear')
                    && ! str_contains($serialized, 'access_token');
            });
    }

    public function test_log_request_failure_logs_warning_with_error(): void
    {
        Log::spy();

        (new ThirdPartyApiLogger)->logRequest(
            'example',
            'POST',
            'https://example.test/fail',
            microtime(true),
            null,
            new RuntimeException('upstream down'),
            ['upload_token_present' => false],
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Third-party API call failed.'
                    && ($context['error'] ?? null) === 'upstream down'
                    && ($context['upload_token_present'] ?? null) === false;
            });
    }
}

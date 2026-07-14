<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Google\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Repositories\StorageAccountRepository;
use Modules\Storage\Services\StorageQuotaQueryService;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageQuotaQueryServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_snapshot_caches_onedrive_quota_between_calls(): void
    {
        StorageAccount::factory()->create([
            'provider' => 'onedrive',
            'label' => fake()->words(2, true),
            'credentials' => [
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'client_id' => 'client-id',
                'client_secret' => 'client-secret',
                'expires_at' => now()->addHour()->toIso8601String(),
                'granted_scopes' => ['Files.ReadWrite'],
            ],
        ]);

        Http::fake([
            'graph.microsoft.com/v1.0/me/drive*' => Http::response([
                'id' => 'drive-id',
                'quota' => [
                    'used' => 100,
                    'total' => 1000,
                    'remaining' => 900,
                ],
            ], 200),
        ]);

        $service = app(StorageQuotaQueryService::class);

        $first = $service->snapshot();
        $second = $service->snapshot();

        $this->assertSame('ok', $first['accounts'][0]['status']);
        $this->assertSame($first['accounts'][0]['quota'], $second['accounts'][0]['quota']);
        Http::assertSentCount(1);
    }

    public function test_snapshot_marks_google_photos_and_r2_as_unsupported(): void
    {
        StorageAccount::factory()->googlePhotos()->create();
        StorageAccount::factory()->r2()->create();

        $snapshot = app(StorageQuotaQueryService::class)->snapshot();

        $this->assertCount(2, $snapshot['accounts']);
        $this->assertSame('unsupported', $snapshot['accounts'][0]['status']);
        $this->assertSame('unsupported', $snapshot['accounts'][1]['status']);
        Http::assertNothingSent();
    }

    public function test_snapshot_reports_onedrive_http_errors(): void
    {
        StorageAccount::factory()->oneDrive()->create();

        Http::fake([
            'graph.microsoft.com/v1.0/me/drive*' => Http::response(['error' => ['message' => 'Forbidden']], 403),
        ]);

        $snapshot = app(StorageQuotaQueryService::class)->snapshot();

        $this->assertSame('error', $snapshot['accounts'][0]['status']);
        $this->assertStringContainsString('HTTP 403', (string) $snapshot['accounts'][0]['message']);
    }

    public function test_snapshot_reports_onedrive_missing_quota_payload(): void
    {
        StorageAccount::factory()->oneDrive()->create();

        Http::fake([
            'graph.microsoft.com/v1.0/me/drive*' => Http::response(['id' => 'drive-id'], 200),
        ]);

        $snapshot = app(StorageQuotaQueryService::class)->snapshot();

        $this->assertSame('unsupported', $snapshot['accounts'][0]['status']);
        $this->assertSame('OneDrive did not return storage quota.', $snapshot['accounts'][0]['message']);
    }

    public function test_snapshot_reports_google_drive_quota(): void
    {
        StorageAccount::factory()->googleDrive()->create();

        $this->bindGoogleClient([
            [
                'storageQuota' => [
                    'usage' => '2048',
                    'limit' => '10240',
                ],
            ],
        ]);

        $snapshot = app(StorageQuotaQueryService::class)->snapshot();

        $this->assertSame('ok', $snapshot['accounts'][0]['status']);
        $this->assertSame(2048, $snapshot['accounts'][0]['quota']['used_bytes']);
        $this->assertSame(10240, $snapshot['accounts'][0]['quota']['limit_bytes']);
        $this->assertSame(8192, $snapshot['accounts'][0]['quota']['remaining_bytes']);
    }

    public function test_snapshot_reports_google_drive_missing_quota(): void
    {
        StorageAccount::factory()->googleDrive()->create();

        $this->bindGoogleClient([
            [],
        ]);

        $snapshot = app(StorageQuotaQueryService::class)->snapshot();

        $this->assertSame('unsupported', $snapshot['accounts'][0]['status']);
        $this->assertSame('Google Drive did not return storage quota.', $snapshot['accounts'][0]['message']);
    }

    public function test_snapshot_marks_unknown_provider_as_unsupported(): void
    {
        StorageAccount::factory()->create([
            'provider' => 'legacy-provider',
            'label' => fake()->words(2, true),
        ]);

        $snapshot = app(StorageQuotaQueryService::class)->snapshot();

        $this->assertSame('unsupported', $snapshot['accounts'][0]['status']);
        $this->assertSame('Unknown storage provider.', $snapshot['accounts'][0]['message']);
    }

    public function test_snapshot_reports_google_drive_api_errors(): void
    {
        StorageAccount::factory()->googleDrive()->create([
            'credentials' => [
                'access_token' => '',
                'refresh_token' => '',
                'client_id' => '',
                'client_secret' => '',
                'expires_at' => now()->subMinute()->toIso8601String(),
            ],
        ]);

        $snapshot = app(StorageQuotaQueryService::class)->snapshot();

        $this->assertSame('error', $snapshot['accounts'][0]['status']);
        $this->assertSame('Google credentials are incomplete.', $snapshot['accounts'][0]['message']);
    }

    public function test_snapshot_reports_onedrive_token_errors(): void
    {
        StorageAccount::factory()->oneDrive()->create([
            'credentials' => [
                'access_token' => 'expired',
                'refresh_token' => 'refresh-token',
                'client_id' => 'client-id',
                'client_secret' => 'client-secret',
                'expires_at' => now()->subMinute()->toIso8601String(),
            ],
        ]);

        Http::fake([
            'login.microsoftonline.com/common/oauth2/v2.0/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $snapshot = app(StorageQuotaQueryService::class)->snapshot();

        $this->assertSame('error', $snapshot['accounts'][0]['status']);
        $this->assertSame('OneDrive token refresh failed.', $snapshot['accounts'][0]['message']);
        Http::assertSentCount(1);
    }

    public function test_snapshot_returns_empty_accounts_when_none_configured(): void
    {
        $snapshot = app(StorageQuotaQueryService::class)->snapshot();

        $this->assertSame([], $snapshot['accounts']);
        $this->assertNotEmpty($snapshot['generated_at']);
    }

    public function test_snapshot_ignores_invalid_cached_payload(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create();

        Cache::put('xflickr:storage:quota:'.$account->id, [
            'status' => 'bogus',
        ], 120);

        Http::fake([
            'graph.microsoft.com/v1.0/me/drive*' => Http::response([
                'quota' => ['used' => 50, 'total' => 500],
            ], 200),
        ]);

        $snapshot = app(StorageQuotaQueryService::class)->snapshot();

        $this->assertSame('ok', $snapshot['accounts'][0]['status']);
        $this->assertSame(50, $snapshot['accounts'][0]['quota']['used_bytes']);
        Http::assertSentCount(1);
    }

    public function test_snapshot_ignores_cached_payload_with_invalid_message_type(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create();

        Cache::put('xflickr:storage:quota:'.$account->id, [
            'status' => 'ok',
            'message' => ['not', 'a', 'string'],
            'quota' => ['used_bytes' => 10, 'limit_bytes' => 100, 'remaining_bytes' => 90],
        ], 120);

        Http::fake([
            'graph.microsoft.com/v1.0/me/drive*' => Http::response([
                'quota' => ['used' => 50, 'total' => 500],
            ], 200),
        ]);

        $snapshot = app(StorageQuotaQueryService::class)->snapshot();

        $this->assertSame('ok', $snapshot['accounts'][0]['status']);
        $this->assertSame(50, $snapshot['accounts'][0]['quota']['used_bytes']);
        Http::assertSentCount(1);
    }

    public function test_snapshot_uses_cached_payload_without_quota_block(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create();

        Cache::put('xflickr:storage:quota:'.$account->id, [
            'status' => 'unsupported',
            'message' => 'Cached unsupported',
            'quota' => null,
        ], 120);

        Http::fake();

        $snapshot = app(StorageQuotaQueryService::class)->snapshot();

        $this->assertSame('unsupported', $snapshot['accounts'][0]['status']);
        $this->assertSame('Cached unsupported', $snapshot['accounts'][0]['message']);
        $this->assertNull($snapshot['accounts'][0]['quota']);
        Http::assertNothingSent();
    }

    public function test_snapshot_ignores_cached_payload_with_invalid_quota_shape(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create();

        Cache::put('xflickr:storage:quota:'.$account->id, [
            'status' => 'ok',
            'message' => null,
            'quota' => ['limit_bytes' => 100],
        ], 120);

        Http::fake([
            'graph.microsoft.com/v1.0/me/drive*' => Http::response([
                'quota' => ['used' => 75, 'total' => 500],
            ], 200),
        ]);

        $snapshot = app(StorageQuotaQueryService::class)->snapshot();

        $this->assertSame('ok', $snapshot['accounts'][0]['status']);
        $this->assertSame(75, $snapshot['accounts'][0]['quota']['used_bytes']);
        Http::assertSentCount(1);
    }

    /**
     * @param  list<array<string, mixed>>  $payloads
     */
    private function bindGoogleClient(array $payloads): void
    {
        $responses = array_map(
            fn (array $payload): Response => new Response(
                200,
                ['Content-Type' => 'application/json'],
                (string) json_encode($payload, JSON_THROW_ON_ERROR),
            ),
            $payloads,
        );

        $handler = HandlerStack::create(new MockHandler($responses));
        $googleClient = new Client;
        $googleClient->setHttpClient(new \GuzzleHttp\Client(['handler' => $handler]));
        $googleClient->setAccessToken([
            'access_token' => fake()->sha256(),
            'created' => time(),
            'expires_in' => 3600,
        ]);

        $accounts = app(StorageAccountRepository::class);
        $this->app->instance(
            GoogleTokenService::class,
            new class($googleClient, $accounts) extends GoogleTokenService
            {
                public function __construct(
                    private readonly Client $boundClient,
                    StorageAccountRepository $accounts,
                ) {
                    parent::__construct($accounts);
                }

                public function clientForAccount(array $credentials, StorageAccount $account): Client
                {
                    $this->accessToken($credentials, $account);

                    return $this->boundClient;
                }
            },
        );
    }
}

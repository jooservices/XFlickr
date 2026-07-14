<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Feature\Controllers\Api\V1;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageQuotaControllerTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_quota_snapshot_returns_empty_accounts_when_none_connected(): void
    {
        $response = $this->getJson('/api/v1/storage/quota');

        $response->assertOk();
        $response->assertJsonPath('data.accounts', []);
        $response->assertJsonStructure([
            'data' => [
                'generated_at',
                'accounts',
            ],
        ]);
    }

    public function test_quota_snapshot_marks_unsupported_providers(): void
    {
        StorageAccount::factory()->create([
            'provider' => 'google_photos',
            'label' => fake()->words(2, true),
            'is_default' => true,
        ]);

        StorageAccount::factory()->create([
            'provider' => 'r2',
            'label' => fake()->words(2, true),
            'credentials' => [
                'access_key_id' => fake()->sha256(),
                'secret_access_key' => fake()->sha256(),
                'bucket' => fake()->slug(),
                'endpoint' => 'https://'.fake()->domainName(),
            ],
        ]);

        $response = $this->getJson('/api/v1/storage/quota');

        $response->assertOk();
        $response->assertJsonCount(2, 'data.accounts');
        $response->assertJsonPath('data.accounts.0.status', 'unsupported');
        $response->assertJsonPath('data.accounts.1.status', 'unsupported');
        $response->assertJsonPath('data.accounts.0.quota', null);
    }

    public function test_quota_snapshot_returns_onedrive_usage(): void
    {
        $account = StorageAccount::factory()->create([
            'provider' => 'onedrive',
            'label' => fake()->words(2, true),
            'is_default' => true,
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
                    'used' => 12_884_901_888,
                    'total' => 53_687_091_200,
                    'remaining' => 40_802_189_312,
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/storage/quota');

        $response->assertOk();
        $response->assertJsonPath('data.accounts.0.account.id', $account->id);
        $response->assertJsonPath('data.accounts.0.status', 'ok');
        $response->assertJsonPath('data.accounts.0.quota.used_bytes', 12_884_901_888);
        $response->assertJsonPath('data.accounts.0.quota.limit_bytes', 53_687_091_200);
        $response->assertJsonPath('data.accounts.0.quota.remaining_bytes', 40_802_189_312);
    }
}

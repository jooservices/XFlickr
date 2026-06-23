<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\StorageDownloadStreamer;
use App\Enums\StorageDriver;
use App\Models\StorageAccount;
use App\Services\Storage\StorageAccountScopeService;
use App\Services\Storage\StorageR2ConnectionVerifier;
use App\Support\Storage\StorageStreamResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StorageR2Test extends TestCase
{
    use RefreshDatabase;

    public function test_r2_driver_does_not_require_oauth_or_app_credentials(): void
    {
        $driver = StorageDriver::R2;

        $this->assertFalse($driver->requiresOAuth());
        $this->assertFalse($driver->requiresApp());
        $this->assertSame('r2', $driver->routeSlug());
        $this->assertSame([], $driver->defaultScopes());
    }

    public function test_r2_account_does_not_need_reauthorization(): void
    {
        $account = StorageAccount::query()->create([
            'provider' => StorageDriver::R2->value,
            'label' => 'R2 bucket',
            'credentials' => [
                'access_key_id' => 'key',
                'secret_access_key' => 'secret',
                'bucket' => 'photos',
                'endpoint' => 'https://example.r2.cloudflarestorage.com',
            ],
            'connected_at' => now(),
        ]);

        $service = app(StorageAccountScopeService::class);

        $this->assertFalse($service->needsReauthorization($account));
        $this->assertSame([], $service->missingScopes($account));
    }

    public function test_can_connect_r2_account_with_valid_credentials(): void
    {
        $this->mock(StorageR2ConnectionVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')->once();
        });

        $response = $this->post('/storage/connect/r2', [
            'label' => 'Archive bucket',
            'access_key_id' => 'test-access-key',
            'secret_access_key' => 'test-secret-key',
            'bucket' => 'xflickr-archive',
            'endpoint' => 'https://example.r2.cloudflarestorage.com',
            'region' => 'auto',
            'prefix' => 'uploads',
        ]);

        $response->assertRedirect(route('settings.index', ['tab' => 'storage']));

        $account = StorageAccount::query()->where('provider', StorageDriver::R2->value)->first();
        $this->assertNotNull($account);
        $this->assertSame('Archive bucket', $account->label);
        $this->assertSame('xflickr-archive', $account->credentials['bucket'] ?? null);
        $this->assertSame('uploads', $account->credentials['prefix'] ?? null);
    }

    public function test_connect_r2_requires_bucket(): void
    {
        $response = $this->post('/storage/connect/r2', [
            'label' => 'Archive bucket',
            'access_key_id' => 'test-access-key',
            'secret_access_key' => 'test-secret-key',
            'endpoint' => 'https://example.r2.cloudflarestorage.com',
        ]);

        $response->assertSessionHasErrors('bucket');
    }

    public function test_r2_browse_requires_account_id(): void
    {
        $response = $this->getJson('/api/storage/r2/browse');

        $response->assertStatus(422);
        $response->assertJson(['message' => 'account_id is required.']);
    }

    public function test_r2_download_requires_path(): void
    {
        $account = StorageAccount::query()->create([
            'provider' => StorageDriver::R2->value,
            'label' => 'R2 bucket',
            'credentials' => [
                'access_key_id' => 'key',
                'secret_access_key' => 'secret',
                'bucket' => 'photos',
                'endpoint' => 'https://example.r2.cloudflarestorage.com',
            ],
            'connected_at' => now(),
        ]);

        $response = $this->getJson('/api/storage/r2/download?account_id='.$account->id);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'path is required.']);
    }

    public function test_r2_download_streams_existing_file(): void
    {
        $account = $this->r2Account(['prefix' => 'archive']);
        $stream = fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);
        fwrite($stream, 'file-bytes');
        rewind($stream);

        $this->app->instance(StorageDownloadStreamer::class, new class($stream) implements StorageDownloadStreamer
        {
            /**
             * @param  resource  $stream
             */
            public function __construct(private mixed $stream) {}

            public function openStreamForAccount(StorageAccount $account, string $remotePath): ?StorageStreamResult
            {
                return new StorageStreamResult($this->stream, 'image.txt', 'text/plain');
            }
        });

        $response = $this->get('/api/storage/r2/download?account_id='.$account->id.'&path=photos/image.txt');

        $response->assertOk();
        $this->assertSame('file-bytes', $response->streamedContent());
        $response->assertHeader('Content-Disposition', 'attachment; filename="image.txt"');
    }

    public function test_r2_download_returns_not_found_for_missing_file(): void
    {
        $account = $this->r2Account(['prefix' => 'archive']);
        $this->app->instance(StorageDownloadStreamer::class, new class implements StorageDownloadStreamer
        {
            public function openStreamForAccount(StorageAccount $account, string $remotePath): ?StorageStreamResult
            {
                return null;
            }
        });

        $response = $this->getJson('/api/storage/r2/download?account_id='.$account->id.'&path=missing.jpg');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Remote file not found.']);
    }

    public function test_download_returns_unsupported_for_non_r2_provider(): void
    {
        $account = StorageAccount::query()->create([
            'provider' => StorageDriver::GoogleDrive->value,
            'label' => 'Drive',
            'credentials' => [
                'granted_scopes' => StorageDriver::GoogleDrive->defaultScopes(),
            ],
            'connected_at' => now(),
        ]);

        $response = $this->getJson('/api/storage/google-drive/download?account_id='.$account->id.'&path=file.jpg');

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Download is not supported for this provider yet.']);
    }

    public function test_settings_page_includes_r2_connection_meta(): void
    {
        $account = StorageAccount::query()->create([
            'provider' => StorageDriver::R2->value,
            'label' => 'Archive bucket',
            'credentials' => [
                'access_key_id' => 'key',
                'secret_access_key' => 'secret',
                'bucket' => 'xflickr-archive',
                'endpoint' => 'https://example.r2.cloudflarestorage.com',
                'prefix' => 'uploads',
            ],
            'connected_at' => now(),
        ]);

        $response = $this->get('/settings?tab=storage');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->has('storage_accounts', 1)
            ->where('storage_accounts.0.id', $account->id)
            ->where('storage_accounts.0.connection_meta.bucket', 'xflickr-archive')
            ->where('storage_accounts.0.connection_meta.endpoint', 'https://example.r2.cloudflarestorage.com')
            ->where('storage_accounts.0.connection_meta.prefix', 'uploads')
            ->where('storage_drivers.3.value', StorageDriver::R2->value)
            ->where('storage_drivers.3.requires_oauth', false));
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function r2Account(array $credentials = []): StorageAccount
    {
        return StorageAccount::query()->create([
            'provider' => StorageDriver::R2->value,
            'label' => 'R2 bucket',
            'credentials' => [
                'access_key_id' => 'key',
                'secret_access_key' => 'secret',
                'bucket' => 'photos',
                'endpoint' => 'https://example.r2.cloudflarestorage.com',
                ...$credentials,
            ],
            'connected_at' => now(),
        ]);
    }
}

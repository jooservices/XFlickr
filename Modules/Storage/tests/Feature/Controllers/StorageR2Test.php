<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Feature\Controllers;

use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageAccountScopeService;
use Modules\Storage\Support\StorageR2Config;
use Modules\Storage\Tests\TestCase;

final class StorageR2Test extends TestCase
{
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
        $this->bindInMemoryDisk(function ($factory): void {
            $factory->shouldReceive('verifyR2Credentials')->once();
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

        $response->assertRedirect(route('connections.index', ['provider' => 'storage']));

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
        $response = $this->getJson('/api/v1/storage/r2/files');

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

        $response = $this->getJson('/api/v1/storage/r2/files/download?account_id='.$account->id);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'path is required.']);
    }

    public function test_r2_download_streams_existing_file(): void
    {
        $account = $this->r2Account(['prefix' => 'archive']);
        ['disk' => $disk] = $this->bindInMemoryDisk();
        $key = StorageR2Config::from($account->credentials ?? [])->objectKey('photos/image.txt');
        $disk->put($key, 'file-bytes');

        $response = $this->get('/api/v1/storage/r2/files/download?account_id='.$account->id.'&path=photos/image.txt');

        $response->assertOk();
        $this->assertSame('file-bytes', $response->streamedContent());
        $response->assertHeader('Content-Disposition', 'attachment; filename=image.txt');
    }

    public function test_download_filename_cannot_inject_response_headers(): void
    {
        $account = $this->r2Account();
        ['disk' => $disk] = $this->bindInMemoryDisk();
        $remotePath = 'photos/file".txt';
        $key = StorageR2Config::from($account->credentials ?? [])->objectKey($remotePath);
        $disk->put($key, 'file-bytes');

        $response = $this->get('/api/v1/storage/r2/files/download?account_id='.$account->id.'&path='.urlencode($remotePath));

        $response->assertOk();
        $response->assertHeaderMissing('X-Injected-Header');
        $this->assertStringNotContainsString("\r", (string) $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString("\n", (string) $response->headers->get('Content-Disposition'));
    }

    public function test_r2_download_returns_not_found_for_missing_file(): void
    {
        $account = $this->r2Account(['prefix' => 'archive']);
        $this->bindInMemoryDisk();

        $response = $this->getJson('/api/v1/storage/r2/files/download?account_id='.$account->id.'&path=missing.jpg');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Remote file not found.']);
    }

    public function test_download_returns_unprocessable_for_non_streamable_provider(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();

        $response = $this->getJson('/api/v1/storage/google-photos/files/download?account_id='.$account->id.'&path=file.jpg');

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Storage provider [google_photos] does not support [stream].']);
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

        $response = $this->get('/connections?provider=storage');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Connections/Index')
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

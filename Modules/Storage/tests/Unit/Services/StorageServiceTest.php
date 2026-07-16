<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Aws\S3\S3Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Mockery;
use Modules\Storage\Dto\StorageUploadRequest;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageService;
use Modules\Storage\Support\StorageR2Config;
use Modules\Storage\Tests\TestCase;
use RuntimeException;

final class StorageServiceTest extends TestCase
{
    public function test_accounts_returns_collection(): void
    {
        $accounts = app(StorageService::class)->accounts();

        $this->assertInstanceOf(Collection::class, $accounts);
    }

    public function test_drivers_lists_all_storage_drivers(): void
    {
        $drivers = app(StorageService::class)->drivers();

        $this->assertNotEmpty($drivers);
        $this->assertArrayHasKey('value', $drivers->first());
        $this->assertArrayHasKey('label', $drivers->first());
    }

    public function test_apps_returns_collection(): void
    {
        $apps = app(StorageService::class)->apps();

        $this->assertInstanceOf(Collection::class, $apps);
    }

    public function test_redirects_returns_provider_map(): void
    {
        $redirects = app(StorageService::class)->redirects();

        $this->assertIsArray($redirects);
    }

    public function test_upload_delegates_to_google_photos_and_caches_item(): void
    {
        Http::fake([
            'photoslibrary.googleapis.com/v1/uploads' => Http::response('upload-token', 200),
            'photoslibrary.googleapis.com/v1/mediaItems:batchCreate' => Http::response([
                'newMediaItemResults' => [[
                    'mediaItem' => [
                        'id' => 'media-'.fake()->uuid(),
                        'filename' => 'photo.jpg',
                        'mimeType' => 'image/jpeg',
                        'baseUrl' => 'https://example.com/photo',
                        'mediaMetadata' => ['creationTime' => '2026-01-01T00:00:00Z'],
                    ],
                ]],
            ], 200),
        ]);

        $account = StorageAccount::factory()->googlePhotos()->create();
        $tempFile = tempnam(sys_get_temp_dir(), 'xflickr-upload-');
        $this->assertNotFalse($tempFile);
        file_put_contents($tempFile, 'image-bytes');

        try {
            $result = app(StorageService::class)->upload(
                $account->id,
                new StorageUploadRequest($tempFile, 'Flickr/photo.jpg'),
            );
        } finally {
            @unlink($tempFile);
        }

        $this->assertNotEmpty($result->id);
        $this->assertDatabaseHas('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => $result->id,
        ]);
    }

    public function test_upload_routes_flysystem_providers_without_cache_upsert(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $objectKey = StorageR2Config::from($account->credentials ?? [])->objectKey('remote/photo.jpg');

        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('headObject')->once()->andThrow(new RuntimeException('missing object'));

        ['disk' => $disk] = $this->bindInMemoryDisk(function ($factory) use ($client): void {
            $factory->shouldReceive('r2Client')->once()->andReturn($client);
        });

        $tempFile = tempnam(sys_get_temp_dir(), 'xflickr-r2-upload-');
        $this->assertNotFalse($tempFile);
        file_put_contents($tempFile, 'image-bytes');

        try {
            $result = app(StorageService::class)->upload(
                $account->id,
                new StorageUploadRequest($tempFile, 'remote/photo.jpg'),
            );
        } finally {
            @unlink($tempFile);
        }

        $this->assertSame('remote/photo.jpg', $result->path);
        $this->assertNull($result->etag);
        $this->assertTrue($disk->exists($objectKey));
        $this->assertDatabaseMissing('storage_remote_items', [
            'storage_account_id' => $account->id,
        ]);
    }

    public function test_open_stream_for_account_delegates_to_transfer_driver(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $key = StorageR2Config::from($account->credentials ?? [])->objectKey('photos/image.jpg');

        ['disk' => $disk] = $this->bindInMemoryDisk();
        $disk->put($key, 'image-bytes');

        $result = app(StorageService::class)->openStreamForAccount($account, 'photos/image.jpg');

        $this->assertNotNull($result);
        $this->assertSame('image.jpg', $result->filename);
    }

    public function test_delete_rejects_empty_item_ids(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one item id is required.');

        app(StorageService::class)->delete($account, []);
    }

    public function test_delete_filters_blank_ids_before_delegating(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $itemId = fake()->uuid();

        $this->bindGoogleClient([[204]]);

        $result = app(StorageService::class)->delete(
            $account,
            ['', $itemId],
        );

        $this->assertSame([$itemId], $result['deleted']);
    }

    public function test_verify_connection_returns_report_for_account(): void
    {
        $account = StorageAccount::factory()->r2()->create();

        $this->bindInMemoryDisk(function ($factory): void {
            $factory->shouldReceive('verifyR2Credentials')->once();
        });

        $report = app(StorageService::class)->verifyConnection($account->id);

        $this->assertNotNull($report);
        $this->assertSame($account->id, $report->accountId);
        $this->assertTrue($report->healthy());
    }

    public function test_verify_connection_returns_null_for_unknown_account(): void
    {
        $this->assertNull(app(StorageService::class)->verifyConnection(999));
    }
}
